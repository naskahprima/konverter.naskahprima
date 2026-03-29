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
    public function create()
    {
        $user = Auth::user();

        if ($user->token_balance <= 0) {
            return redirect()->route('conversions.index')
                ->with('error', 'Token kamu habis! Hubungi admin untuk top-up.');
        }

        return view('conversions.create', ['tokenBalance' => $user->token_balance]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'naskah'           => 'required|file|mimes:pdf,doc,docx|max:20480',
            'template'         => 'required|file|mimes:doc,docx|max:10240',
            'document_type'    => 'nullable|in:skripsi,jurnal,paper,artikel,tesis',
            'author_guide_url' => 'nullable|url|max:2048',
            'archive_urls'     => 'nullable|string|max:5000',
        ], [
            'naskah.required' => 'File naskah akademik wajib diupload.',
            'naskah.mimes'    => 'File naskah harus berformat PDF, DOC, atau DOCX.',
            'naskah.max'      => 'Ukuran file naskah maksimal 20MB.',
            'template.required' => 'Template jurnal target wajib diupload.',
            'template.mimes'    => 'Template harus berformat DOC atau DOCX.',
        ]);

        $user = Auth::user();

        if ($user->token_balance <= 0) {
            return redirect()->back()->with('error', 'Token kamu habis!');
        }

        $user->decrement('token_balance');

        $archiveUrls = [];
        if ($request->filled('archive_urls')) {
            $archiveUrls = array_values(array_filter(
                array_map('trim', explode("\n", $request->archive_urls)),
                fn($url) => filter_var($url, FILTER_VALIDATE_URL)
            ));
        }

        $conversion = Conversion::create([
            'user_id'          => $user->id,
            'document_type'    => $request->document_type ?? 'skripsi',
            'author_guide_url' => $request->author_guide_url,
            'archive_urls'     => $archiveUrls,
            'status'           => Conversion::STATUS_PENDING,
        ]);

        // Simpan dengan type 'naskah' (support lama 'skripsi' tetap lewat model)
        $this->storeFile($request->file('naskah'),  $conversion->id, 'naskah');
        $this->storeFile($request->file('template'), $conversion->id, 'template');

        ProcessConversionJob::dispatch($conversion->id, 'analyze');

        return redirect()->route('conversions.show', $conversion->id)
            ->with('success', 'Upload berhasil! AI sedang menganalisis naskahmu...');
    }

    public function show(Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        $conversion->load([
            'files',
            'messages' => fn($q) => $q->orderBy('created_at'),
        ]);

        return view('conversions.show', compact('conversion'));
    }

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

        $updates = ['status' => Conversion::STATUS_PENDING];

        if ($request->fallback_type === 'author_guide_manual') {
            $updates['author_guide_fallback'] = false;
        }

        $conversion->update($updates);

        ProcessConversionJob::dispatch($conversion->id, 'analyze');

        return response()->json([
            'success' => true,
            'message' => 'File diterima! Analisis dilanjutkan...',
        ]);
    }

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

        ProcessConversionJob::dispatch($conversion->id, 'convert');

        return response()->json([
            'success' => true,
            'message' => 'Jawaban diterima! AI mulai menulis jurnal...',
        ]);
    }

    public function poll(Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        $conversion->load([
            'messages' => fn($q) => $q->orderBy('created_at'),
        ]);

        return response()->json([
            'status'                => $conversion->status,
            'status_label'          => $conversion->statusLabel(),
            'is_completed'          => $conversion->status === Conversion::STATUS_COMPLETED,
            'is_failed'             => $conversion->status === Conversion::STATUS_FAILED,
            'is_rejected'           => $conversion->status === Conversion::STATUS_REJECTED,
            'needs_user_action'     => $conversion->needsUserAction(),
            'author_guide_fallback' => $conversion->author_guide_fallback,
            'archive_fallback'      => $conversion->archive_fallback,
            'scope_match'           => $conversion->scope_match,
            'scope_match_percentage' => $conversion->getScopeMatchPercentage(),
            'title_recommendations' => $conversion->title_recommendations ?? [],
            'qa_questions'          => $conversion->qa_questions ?? [],
            'messages'              => $conversion->messages->map(fn($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'type'       => $m->type,
                'content'    => $m->content,
                'created_at' => $m->created_at->format('H:i'),
            ]),
        ]);
    }

    public function result(Conversion $conversion)
    {
        $this->authorizeConversion($conversion);

        if ($conversion->status !== Conversion::STATUS_COMPLETED) {
            return redirect()->route('conversions.show', $conversion->id);
        }

        $checklist = json_decode($conversion->submission_checklist ?? '[]', true) ?? [];

        return view('conversions.result', compact('conversion', 'checklist'));
    }

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

    public function index()
    {
        $conversions = Conversion::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('conversions.index', compact('conversions'));
    }

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