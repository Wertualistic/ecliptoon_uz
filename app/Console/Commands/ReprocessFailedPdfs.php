<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Chapter;
use App\Jobs\ProcessChapterPdf;

class ReprocessFailedPdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chapters:reprocess-failed-pdfs 
                            {--sync : Process PDF page-slicing synchronously in-process instead of queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find all chapters with a PDF path but empty/missing pages, and re-run the image processing job';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Scanning database for chapters that have a PDF path but no generated image pages...");

        $chapters = Chapter::whereNotNull('pdf_path')
            ->where(function($query) {
                $query->whereNull('pages')
                    ->orWhere('pages', '')
                    ->orWhere('pages', '[]')
                    ->orWhere('pages', '[""]');
            })
            ->with('series')
            ->get();

        if ($chapters->isEmpty()) {
            $this->info("All chapters with PDFs have already been successfully processed (no empty pages lists found).");
            return 0;
        }

        $this->warn("Found " . $chapters->count() . " chapter(s) that need reprocessing.");

        $sync = $this->option('sync');

        foreach ($chapters as $chapter) {
            $this->comment("--------------------------------------------------");
            $this->info("Reprocessing Chapter ID: {$chapter->id}");
            $this->line("Series: {$chapter->series->title}");
            $this->line("Chapter: {$chapter->chapter_number}");
            $this->line("PDF Path: {$chapter->pdf_path}");

            if ($sync) {
                $this->info("Processing PDF into WebP/JPEG images synchronously...");
                try {
                    ProcessChapterPdf::dispatchSync($chapter);
                    $this->info("Successfully processed chapter {$chapter->id}!");
                } catch (\Exception $e) {
                    $this->error("Failed to process chapter {$chapter->id}: " . $e->getMessage());
                }
            } else {
                $this->info("Dispatching PDF processing job to the queue...");
                ProcessChapterPdf::dispatch($chapter);
            }
        }

        $this->comment("--------------------------------------------------");
        if ($sync) {
            $this->info("Reprocessing complete!");
        } else {
            $this->info("All reprocessing jobs have been dispatched to the queue. Make sure your queue worker (php artisan queue:work) is running.");
        }

        return 0;
    }
}
