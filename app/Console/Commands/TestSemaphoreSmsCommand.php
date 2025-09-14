<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SmsService;

class TestSemaphoreSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test-semaphore {phone} {message?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMS functionality with Semaphore';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message') ?? 'Test SMS from Auto Repair Shop System';
        
        $this->info('Testing Semaphore SMS functionality...');
        $this->info('Phone: ' . $phone);
        $this->info('Message: ' . $message);
        
        // Check environment variables
        $this->info("\n=== Environment Check ===");
        $apiKey = env('SEMAPHORE_API_KEY');
        $senderName = env('SEMAPHORE_SENDER_NAME', 'AutoRepair');
        
        $this->info('SEMAPHORE_API_KEY: ' . ($apiKey ? 'Set (' . substr($apiKey, 0, 8) . '...)' : 'NOT SET'));
        $this->info('SEMAPHORE_SENDER_NAME: ' . $senderName);
        
        if (!$apiKey) {
            $this->error('❌ Semaphore API key is not configured!');
            $this->info('Please add to your .env file:');
            $this->info('SEMAPHORE_API_KEY=your_semaphore_api_key');
            $this->info('SEMAPHORE_SENDER_NAME=YourSenderName (optional, defaults to AutoRepair)');
            return 1;
        }
        
        // Test phone number formatting
        $this->info("\n=== Phone Number Formatting ===");
        $smsService = app(SmsService::class);
        $formattedPhone = $smsService->toE164($phone);
        $this->info('Original phone: ' . $phone);
        $this->info('E.164 formatted phone: ' . ($formattedPhone ?: 'NULL'));
        
        if (!$formattedPhone) {
            $this->error('❌ Phone number could not be formatted!');
            return 1;
        }
        
        // Test SMS sending
        $this->info("\n=== Sending SMS via Semaphore ===");
        try {
            $result = $smsService->send($formattedPhone, $message);
            if ($result) {
                $this->info('✅ SMS sent successfully via Semaphore!');
                $this->info('Check your phone for the message.');
            } else {
                $this->error('❌ SMS sending failed (returned false)');
                $this->info('Check the logs for more details: storage/logs/laravel.log');
            }
        } catch (\Exception $e) {
            $this->error('❌ SMS sending failed with exception:');
            $this->error($e->getMessage());
        }
        
        return 0;
    }
}
