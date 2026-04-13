<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $htmlBody;

    public function __construct(string $subjectLine, string $htmlBody)
    {
        $this->subjectLine = $subjectLine;
        $this->htmlBody = $htmlBody;
        $this->subject($this->subjectLine);
    }

    public function build()
    {
        return $this->html($this->htmlBody);
    }
}

