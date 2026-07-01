<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Chapter;
use Illuminate\Support\Facades\Log;

class ProcessChapterPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chapter;
    public $timeout = 600; // Allow 10 minutes for large PDFs

    /**
     * Create a new job instance.
     */
    public function __construct(Chapter $chapter)
    {
        $this->chapter = $chapter;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $chapter = $this->chapter;
        Log::info("Starting PDF processing for chapter {$chapter->id}");

        if (!$chapter->pdf_path) {
            Log::error("No PDF path found for chapter {$chapter->id}");
            return;
        }

        $storagePath = storage_path('app/public/');
        $fullPdfPath = $storagePath . $chapter->pdf_path;

        if (!file_exists($fullPdfPath)) {
            Log::error("PDF file does not exist at path: {$fullPdfPath}");
            throw new \Exception("PDF file does not exist: {$fullPdfPath}");
        }

        $outputDir = 'chapters/' . $chapter->id . '_images_' . time();
        $fullOutputDir = $storagePath . $outputDir;

        if (!file_exists($fullOutputDir)) {
            if (!mkdir($fullOutputDir, 0755, true)) {
                Log::error("Failed to create output directory: {$fullOutputDir}");
                throw new \Exception("Failed to create output directory: {$fullOutputDir}");
            }
        }

        // Discover absolute paths for CLI tools if possible
        $pdftoppmBin = $this->findCommand('pdftoppm');
        $cwebpBin = $this->findCommand('cwebp');

        Log::info("Using pdftoppm binary: {$pdftoppmBin}");
        Log::info("Using cwebp binary: {$cwebpBin}");

        // Generate images as JPEG using pdftoppm (150 DPI is a good balance of quality and size)
        $prefix = 'page';
        $cmd = "{$pdftoppmBin} -jpeg -r 150 " . escapeshellarg($fullPdfPath) . " " . escapeshellarg($fullOutputDir . '/' . $prefix);
        
        Log::info("Running command: {$cmd}");
        $pdftoppmOutput = [];
        $pdftoppmStatus = 0;
        exec($cmd . ' 2>&1', $pdftoppmOutput, $pdftoppmStatus);

        if ($pdftoppmStatus !== 0) {
            Log::error("pdftoppm command failed with exit status {$pdftoppmStatus}. Output: " . implode("\n", $pdftoppmOutput));
            throw new \Exception("pdftoppm command failed with exit status {$pdftoppmStatus}");
        }

        // Convert generated JPEGs to WebP if cwebp is available
        $pages = [];
        $files = glob($fullOutputDir . '/' . $prefix . '-*.jpg');
        natsort($files);

        Log::info("pdftoppm generated " . count($files) . " JPEG pages.");

        if (empty($files)) {
            Log::error("pdftoppm ran successfully but no JPEG files were created in {$fullOutputDir}");
            throw new \Exception("No JPEG files generated from PDF");
        }

        foreach ($files as $index => $img) {
            $webpPath = str_replace('.jpg', '.webp', $img);
            
            $cwebpOutput = [];
            $cwebpStatus = 0;
            $cwebpCmd = "{$cwebpBin} -q 65 -resize 800 0 " . escapeshellarg($img) . " -o " . escapeshellarg($webpPath);
            
            exec($cwebpCmd . ' 2>&1', $cwebpOutput, $cwebpStatus);

            if ($cwebpStatus === 0 && file_exists($webpPath)) {
                // Successfully converted to WebP
                @unlink($img); // delete original jpg
                $pages[] = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($storagePath, '', $webpPath));
            } else {
                // Log warning and fallback to original JPEG
                Log::warning("cwebp conversion failed for {$img} (exit status {$cwebpStatus}). Output: " . implode("\n", $cwebpOutput) . ". Falling back to JPEG.");
                $pages[] = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($storagePath, '', $img));
            }
        }

        // Update chapter pages
        $chapter->pages = json_encode(array_values($pages));
        $chapter->save();

        Log::info("Finished PDF processing for chapter {$chapter->id}, registered " . count($pages) . " pages.");
    }

    /**
     * Locate a command binary in the system.
     */
    private function findCommand(string $command): string
    {
        if (function_exists('exec')) {
            $whichOutput = [];
            $whichStatus = 0;
            exec("which " . escapeshellarg($command) . " 2>&1", $whichOutput, $whichStatus);
            if ($whichStatus === 0 && !empty($whichOutput)) {
                $path = trim($whichOutput[0]);
                if (file_exists($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        // Fallback paths
        $fallbacks = [
            '/usr/bin/' . $command,
            '/usr/local/bin/' . $command,
            '/bin/' . $command,
            '/opt/homebrew/bin/' . $command,
        ];

        foreach ($fallbacks as $fallback) {
            if (file_exists($fallback) && is_executable($fallback)) {
                return $fallback;
            }
        }

        return $command; // fallback to raw string (system PATH resolution)
    }
}
