<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;

    public function __construct(string $subjectLine = 'Test Email from Auto Repair Shop System')
    {
        $this->subjectLine = $subjectLine;
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.test');
    }
}


