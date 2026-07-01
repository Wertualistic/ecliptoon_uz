<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook {url? : The webhook URL. Defaults to env(APP_URL)/api/telegram/webhook}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set or update the Telegram Bot Webhook URL with Telegram API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
        if (!$botToken) {
            $this->error('TELEGRAM_BOT_TOKEN is not defined in your .env file.');
            return 1;
        }

        $webhookUrl = $this->argument('url');
        if (!$webhookUrl) {
            $appUrl = rtrim(env('APP_URL'), '/');
            $webhookUrl = "{$appUrl}/api/telegram/webhook";
        }

        $this->info("Setting Telegram Webhook to: {$webhookUrl}");

        $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
        
        $response = Http::post($telegramApiUrl, [
            'url' => $webhookUrl
        ]);

        if ($response->failed()) {
            $this->error("Failed to set webhook. Telegram API responded with: " . $response->body());
            return 1;
        }

        $result = $response->json();
        if (isset($result['ok']) && $result['ok']) {
            $this->info("Webhook successfully set! Response: " . $result['description']);
            return 0;
        }

        $this->error("Failed to set webhook. Response: " . json_encode($result));
        return 1;
    }
}
