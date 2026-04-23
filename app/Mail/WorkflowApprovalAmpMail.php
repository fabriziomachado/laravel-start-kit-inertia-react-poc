<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\TextPart;

final class WorkflowApprovalAmpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $emailApprovalPayload
     */
    public function __construct(
        public string $subjectLine,
        array $emailApprovalPayload,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {
        $this->viewData = $emailApprovalPayload;
    }

    public function build(): static
    {
        $mailable = $this
            ->subject($this->subjectLine)
            ->view('emails.workflow.approval-html', $this->viewData)
            ->text('emails.workflow.approval-text', $this->viewData);

        if (filled($this->fromAddress)) {
            $mailable->from($this->fromAddress, $this->fromName);
        }

        return $mailable->withSymfonyMessage(function (Email $message): void {
            $ampHtml = view('emails.workflow.approval-amp', $this->viewData)->render();

            $plainBody = $message->getTextBody();
            $htmlBody = $message->getHtmlBody();
            $plainCharset = $message->getTextCharset() ?: 'utf-8';
            $htmlCharset = $message->getHtmlCharset() ?: 'utf-8';

            $ampPart = new TextPart($ampHtml, 'utf-8', 'x-amp-html');
            $htmlPart = new TextPart($htmlBody, $htmlCharset, 'html');
            $plainPart = new TextPart($plainBody, $plainCharset, 'plain');

            // AMP for Email sob multipart/alternative. Ordem por preferência crescente
            // (RFC 2046 §5.1.4): text/plain → text/html → text/x-amp-html. Gmail escolhe
            // o último suportado; sem AMP registado usa o HTML, nunca cai no texto plano.
            $message->text(null)->html(null)->setBody(new AlternativePart($plainPart, $htmlPart, $ampPart));
        });
    }
}
