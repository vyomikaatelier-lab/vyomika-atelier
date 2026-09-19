<?php

namespace Tests\Unit;

use App\Support\MailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Tests\TestCase;

class MailTransportTest extends TestCase
{
    public function test_smtp_timeout_cannot_be_null_or_unbounded(): void
    {
        $this->assertSame(8, MailTransport::DEFAULT_SMTP_TIMEOUT_SECONDS);
        $this->assertSame(8, MailTransport::smtpTimeoutSeconds(8));
        $this->assertSame(8, MailTransport::smtpTimeoutSeconds(null));
        $this->assertSame(8, MailTransport::smtpTimeoutSeconds(0));
        $this->assertSame(8, MailTransport::smtpTimeoutSeconds(-1));
        $this->assertSame(8, MailTransport::smtpTimeoutSeconds(''));
        $this->assertSame(8, MailTransport::smtpTimeoutSeconds('unbounded'));
        $this->assertSame(8, MailTransport::smtpTimeoutSeconds(false));
        $this->assertSame(20, MailTransport::smtpTimeoutSeconds(60));
        $this->assertSame(20, MailTransport::smtpTimeoutSeconds(MailTransport::MAX_SMTP_TIMEOUT_SECONDS + 5));
        $this->assertSame(5, MailTransport::smtpTimeoutSeconds(5));

        $timeout = config('mail.mailers.smtp.timeout');
        $this->assertIsInt($timeout);
        $this->assertNotNull($timeout);
        $this->assertGreaterThan(0, $timeout);
        $this->assertSame(8, $timeout);
        $this->assertLessThanOrEqual(MailTransport::MAX_SMTP_TIMEOUT_SECONDS, $timeout);

        $phpLimit = (int) ini_get('max_execution_time');
        if ($phpLimit > 0) {
            $this->assertLessThan($phpLimit, $timeout);
        } else {
            $this->assertLessThan(30, $timeout);
        }
    }

    public function test_laravel_smtp_transport_applies_the_finite_socket_timeout(): void
    {
        $mailer = app('mail.manager')->mailer('smtp');
        $transport = $mailer->getSymfonyTransport();

        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $stream = $transport->getStream();
        $this->assertInstanceOf(SocketStream::class, $stream);
        $this->assertSame(8.0, $stream->getTimeout());
        $this->assertGreaterThan(0, $stream->getTimeout());
    }
}
