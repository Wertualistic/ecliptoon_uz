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
            Log::warning("No PDF path found for chapter {$chapter->id}");
            return;
        }

        $storagePath = storage_path('app/public/');
        $fullPdfPath = $storagePath . $chapter->pdf_path;
        $outputDir = 'chapters/' . $chapter->id . '_images_' . time();
        $fullOutputDir = $storagePath . $outputDir;

        if (!file_exists($fullOutputDir)) {
            mkdir($fullOutputDir, 0755, true);
        }

        // Generate images as JPEG using pdftoppm (150 DPI is a good balance of quality and size)
        $prefix = 'page';
        $cmd = "pdftoppm -jpeg -r 150 " . escapeshellarg($fullPdfPath) . " " . escapeshellarg($fullOutputDir . '/' . $prefix);
        exec($cmd);

        // Convert generated JPEGs to WebP
        $pages = [];
        $files = glob($fullOutputDir . '/' . $prefix . '-*.jpg');
        natsort($files);

        foreach ($files as $index => $img) {
            $webpPath = str_replace('.jpg', '.webp', $img);
            // Run cwebp to convert (q=65) and resize to max width 800px to drastically reduce size for webtoons
            exec("cwebp -q 65 -resize 800 0 " . escapeshellarg($img) . " -o " . escapeshellarg($webpPath) . " > /dev/null 2>&1");
            @unlink($img); // delete original jpg

            // Path relative to storage/app/public
            $pages[] = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($storagePath, '', $webpPath));
        }

        // Update chapter pages
        $chapter->pages = json_encode(array_values($pages));
        $chapter->save();

        Log::info("Finished PDF processing for chapter {$chapter->id}, generated " . count($pages) . " pages.");
    }
}
