<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessConversionJob;
use App\Models\Conversion;
use App\Models\ConversionFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ConversionController extends Controller
{
    // ── Form Upload Skripsi ───────────────────────────────────────────────────

    public function create()
    {
        $user = Auth::user();

        if ($user->token_balance <= 0) {
            return redirect()->route('conversions.index')
                ->with('error', 'Token kamu habis! Hubungi admin untuk top-up.');
        }

        return view('conversions.create', ['tokenBalance' => $user->token_balance]);
    }

    // ── Submit Form → Mulai Proses ────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'skripsi'          => 'required|file|mimes:pdf,doc,docx|max:20480',  // 20MB
            'template'         => 'required|file|mimes:doc,docx|max:10240',      // 10MB
            'author_guide_url' => 'nullable|url|max:2048',
            'archive_urls'     => 'nullable|string|max:5000',
        ], [
            'skripsi.required'  => 'File skripsi wajib diupload.',
            'skripsi.mimes'     => 'File skripsi harus berformat PDF, DOC, atau DOCX.',
            'skripsi.max'       => 'Ukuran file skripsi maksimal 20MB.',
            'template.required' => 'Template jurnal wajib diupload.',
            'template.mimes'    => 'Template harus berformat DOC atau DOCX.',
        ]);

        $user = Auth::user();

        if ($user->token_balance <= 0) {
            return redirect()->back()->with('error', 'Token kamu habis!');
        }

        // Kurangi token
        $user->decrement('token_balance');

        // Parse archive URLs (1 URL per baris)
        $archiveUrls = [];
        if ($request->filled('archive_urls')) {
            $archiveUrls = array_values(array_filter(
                array_map('trim', explode("\n", $request->archive_urls)),
                fn($url) => filter_var($url, FILTER_VALIDATE_URL)
            ));
        }

        // Buat record konversi
        $conversion = Conversion::create([
            'user_id'          => $user->id,
            'author_guide_url' => $request->author_guide_url,
            'archive_urls'     => $archiveUrls,
            'status'           => Conversion::STATUS_PENDING,
        ]);

        // Simpan file
        $this->storeFile($request->file('skripsi'),  $conversion->id, 'skripsi');
        $this->storeFile($request->file('template'), $conversion->id, 'template');

        // Dispatch background job
        ProcessConversionJob::dispatch($conversion->id, 'analyze');

        return redirect()->route('conversions.show', $conversion->id)
            ->with('success', 'Upload berhasil! AI sedang menganalisis dokumenmu...');
    }

    // ── Halaman Status & Chat ─────────────────────────────────────────────────

    public function show(Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        $conversion->load([
            'files',
            'messages' => fn($q) => $q->orderBy('created_at'),
        ]);

        return view('conversions.show', compact('conversion'));
    }

    // ── Upload File Fallback ──────────────────────────────────────────────────

    public function uploadFallback(Request $request, Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        $request->validate([
            'fallback_files'   => 'required|array|max:5',
            'fallback_files.*' => 'file|mimes:pdf,doc,docx|max:20480',
            'fallback_type'    => 'required|in:author_guide_manual,archive_manual',
        ]);

        foreach ($request->file('fallback_files') as $file) {
            $this->storeFile($file, $conversion->id, $request->fallback_type);
        }

        // Reset status dan restart analysis
        $updates = ['status' => Conversion::STATUS_PENDING];

        if ($request->fallback_type === 'author_guide_manual') {
            $updates['author_guide_fallback'] = false;
        }

        $conversion->update($updates);

        // Dispatch ulang job analyze
        ProcessConversionJob::dispatch($conversion->id, 'analyze');

        return response()->json([
            'success' => true,
            'message' => 'File diterima! Analisis dilanjutkan...',
        ]);
    }

    // ── Submit Jawaban Q&A → Mulai Konversi ───────────────────────────────────

    public function submitQa(Request $request, Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        $request->validate([
            'selected_title' => 'required|string|max:500',
            'answers'        => 'nullable|array',
        ]);

        $answers = $request->answers ?? [];
        $answers['selected_title'] = $request->selected_title;

        $conversion->update([
            'qa_answers' => $answers,
            'status'     => Conversion::STATUS_PENDING,
        ]);

        // Dispatch job konversi
        ProcessConversionJob::dispatch($conversion->id, 'convert');

        return response()->json([
            'success' => true,
            'message' => 'Jawaban diterima! AI mulai menulis jurnal...',
        ]);
    }

    // ── Polling Status (untuk auto-refresh UI) ────────────────────────────────

    public function poll(Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        $conversion->load([
            'messages' => fn($q) => $q->orderBy('created_at'),
        ]);

        return response()->json([
            'status'              => $conversion->status,
            'status_label'        => $conversion->statusLabel(),
            'is_completed'        => $conversion->status === Conversion::STATUS_COMPLETED,
            'is_failed'           => $conversion->status === Conversion::STATUS_FAILED,
            'needs_user_action'   => $conversion->needsUserAction(),
            'author_guide_fallback' => $conversion->author_guide_fallback,
            'archive_fallback'    => $conversion->archive_fallback,
            'scope_match'         => $conversion->scope_match,
            'title_recommendations' => $conversion->title_recommendations ?? [],
            'qa_questions'        => $conversion->qa_questions ?? [],
            'messages'            => $conversion->messages->map(fn($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'type'       => $m->type,
                'content'    => $m->content,
                'created_at' => $m->created_at->format('H:i'),
            ]),
        ]);
    }

    // ── Halaman Hasil ─────────────────────────────────────────────────────────

    public function result(Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        if ($conversion->status !== Conversion::STATUS_COMPLETED) {
            return redirect()->route('conversions.show', $conversion->id);
        }

        $checklist = json_decode($conversion->submission_checklist ?? '[]', true) ?? [];

        return view('conversions.result', compact('conversion', 'checklist'));
    }

    // ── Download File Output ──────────────────────────────────────────────────

    public function download(Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        if ($conversion->status !== Conversion::STATUS_COMPLETED || !$conversion->output_path) {
            abort(404, 'File belum siap atau tidak ditemukan.');
        }

        if (!Storage::exists($conversion->output_path)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        $filename = 'jurnal-konversi-' . $conversion->id . '-' . now()->format('Ymd') . '.docx';

        return Storage::download($conversion->output_path, $filename);
    }

    // ── Riwayat Konversi User ─────────────────────────────────────────────────

    public function index()
    {
        $conversions = Conversion::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('conversions.index', compact('conversions'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function storeFile($file, int $conversionId, string $type): ConversionFile
    {
        $path = $file->store("conversions/{$conversionId}", 'local');

        return ConversionFile::create([
            'conversion_id' => $conversionId,
            'type'          => $type,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'size'          => $file->getSize(),
        ]);
    }

    private function authorizeConversion(Conversion $conversion): void
    {
        if ($conversion->user_id !== Auth::id()) {
            abort(403, 'Kamu tidak punya akses ke konversi ini.');
        }
    }
}
