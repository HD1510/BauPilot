<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Document;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Verkleinert hochgeladene Bilder auf maximal 2560 Pixel Kantenlänge
 * (Architekturblatt Abschnitt 6) — Fotos von der Baustelle sind sonst
 * schnell 8–12 MB groß. Der Mandantenkontext kommt explizit über die
 * company_id in der Payload (Abschnitt 3), nie aus einer Session.
 */
class ResizeDocumentImage implements ShouldQueue
{
    use Queueable;

    public const MAX_EDGE = 2560;

    private const RESIZABLE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        public int $companyId,
        public int $documentId,
    ) {}

    public function handle(CompanyContext $context): void
    {
        $company = Company::query()->find($this->companyId);

        if ($company === null) {
            return;
        }

        $context->runFor($company, function (): void {
            $document = Document::query()->find($this->documentId);

            if ($document === null || ! in_array($document->mime, self::RESIZABLE_MIMES, true)) {
                return;
            }

            $disk = Storage::disk('documents');
            $contents = $disk->get($document->path);

            if ($contents === null) {
                return;
            }

            $resized = $this->resize($contents, $document->mime);

            if ($resized === null) {
                return; // klein genug oder nicht lesbar — nichts zu tun
            }

            $disk->put($document->path, $resized);
            $document->forceFill(['size' => strlen($resized)])->save();
        });
    }

    /**
     * Gibt null zurück, wenn nichts zu verkleinern ist.
     */
    private function resize(string $contents, string $mime): ?string
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $longEdge = max($width, $height);

        if ($longEdge <= self::MAX_EDGE) {
            imagedestroy($source);

            return null;
        }

        $scale = self::MAX_EDGE / $longEdge;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);

        // Transparenz für PNG/WebP erhalten.
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        ob_start();

        match ($mime) {
            'image/png' => imagepng($target, null, 6),
            'image/webp' => imagewebp($target, null, 82),
            default => imagejpeg($target, null, 82),
        };

        $result = ob_get_clean();
        imagedestroy($target);

        return $result === '' ? null : $result;
    }
}
