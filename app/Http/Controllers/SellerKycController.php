<?php

namespace App\Http\Controllers;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\PlatformEmailNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SellerKycController extends Controller
{
    public function __construct(
        protected PlatformEmailNotifications $platformEmailNotifications
    ) {}

    private static function financeiroKycTabUrl(): string
    {
        return '/financeiro?tab=seus-dados';
    }

    /** Limite por arquivo no KYC (PDFs escaneados podem ser grandes). Alinhe upload_max_filesize/post_max_size no PHP. */
    private const MAX_BYTES = 20 * 1024 * 1024;

    private const MAX_FILE_KB = 20480; // 20 MB (regra Laravel max.* em kilobytes)

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/heic',
        'image/heif',
        'application/pdf',
        'application/x-pdf',
    ];

    public function show(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canAccessSellerPanel()) {
            abort(403);
        }

        $subject = $user->kycSubjectUser();
        if ($subject->kyc_status === User::KYC_APPROVED) {
            return redirect()->route('dashboard')->with('success', 'Sua conta já está verificada.');
        }

        return redirect(self::financeiroKycTabUrl());
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->canAccessSellerPanel()) {
            abort(403);
        }

        $subject = $user->kycSubjectUser();
        if ($subject->kyc_status === User::KYC_APPROVED) {
            return redirect(self::financeiroKycTabUrl())->with('error', 'Conta já verificada.');
        }

        $isPj = $subject->person_type === 'pj';

        $kycFile = ['required', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:jpg,jpeg,png,webp,gif,heic,heif,pdf'];

        $rules = [
            'rg_front' => $kycFile,
            'rg_back' => $kycFile,
        ];
        if ($isPj) {
            $rules['company_document'] = $kycFile;
        }

        $request->validate($rules, [
            'rg_front.max' => 'O arquivo da frente do RG não pode ser maior que 20 MB.',
            'rg_back.max' => 'O arquivo do verso do RG não pode ser maior que 20 MB.',
            'company_document.max' => 'O documento da empresa não pode ser maior que 20 MB.',
        ]);

        $disk = Storage::disk('local');
        $baseDir = 'kyc/'.$subject->id;

        KycDocument::query()->where('user_id', $subject->id)->get()->each(function (KycDocument $old) use ($disk) {
            if ($old->disk_path && $disk->exists($old->disk_path)) {
                $disk->delete($old->disk_path);
            }
            $old->delete();
        });

        try {
            $this->storeFile($subject, $request->file('rg_front'), KycDocument::KIND_RG_FRONT, $disk, $baseDir);
            $this->storeFile($subject, $request->file('rg_back'), KycDocument::KIND_RG_BACK, $disk, $baseDir);

            if ($isPj && $request->hasFile('company_document')) {
                $this->storeFile($subject, $request->file('company_document'), KycDocument::KIND_COMPANY_DOCUMENT, $disk, $baseDir);
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect(self::financeiroKycTabUrl())->with('error', 'Não foi possível processar os arquivos. Use imagem (JPG, PNG, WebP, GIF ou HEIC) ou PDF, máx. 20 MB por arquivo.');
        }

        $subject->forceFill([
            'kyc_status' => User::KYC_PENDING_REVIEW,
            'kyc_rejection_reason' => null,
            'kyc_reviewed_at' => null,
            'kyc_reviewed_by' => null,
        ])->save();

        $this->platformEmailNotifications->kycSubmitted($subject->fresh());

        return redirect(self::financeiroKycTabUrl())->with('success', 'Documentos enviados. Aguarde a análise da plataforma.');
    }

    private function storeFile(User $subject, \Illuminate\Http\UploadedFile $file, string $kind, \Illuminate\Contracts\Filesystem\Filesystem $disk, string $baseDir): void
    {
        $mime = $file->getMimeType();
        if ($mime === 'application/octet-stream' || $mime === '') {
            $mime = $file->getClientMimeType() ?: $mime;
        }
        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }
        if (! is_string($mime) || ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('MIME não permitido.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Arquivo muito grande.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $name = Str::uuid()->toString().'.'.$ext;
        $storedPath = $disk->putFileAs($baseDir, $file, $name);
        if (! is_string($storedPath) || $storedPath === '') {
            throw new \RuntimeException('Falha ao gravar arquivo.');
        }

        KycDocument::query()->create([
            'user_id' => $subject->id,
            'kind' => $kind,
            'disk_path' => $storedPath,
            'original_mime' => $mime,
            'size_bytes' => (int) $file->getSize(),
        ]);
    }
}
