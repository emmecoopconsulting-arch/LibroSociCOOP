<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OutboundMailService
{
    public function configure(): void
    {
        $traditional = AppSetting::string(AppSetting::MAIL_PROVIDER) === 'traditional';
        $host = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_HOST : AppSetting::SMTP_HOST);
        $username = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_USERNAME : AppSetting::SMTP_USERNAME);
        $password = $traditional ? AppSetting::traditionalSmtpPassword() : AppSetting::smtpPassword();
        $from = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_FROM_ADDRESS : AppSetting::SMTP_FROM_ADDRESS);
        $scheme = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_SCHEME : AppSetting::SMTP_SCHEME) ?: 'smtp';
        $port = AppSetting::int($traditional ? AppSetting::TRADITIONAL_SMTP_PORT : AppSetting::SMTP_PORT);
        $fromName = AppSetting::string($traditional ? AppSetting::TRADITIONAL_SMTP_FROM_NAME : AppSetting::SMTP_FROM_NAME);

        if (blank($host) || blank($username) || blank($password) || blank($from)) {
            throw new RuntimeException('Configurazione del provider SMTP attivo incompleta. Compilarla in Impostazioni.');
        }

        config([
            'mail.mailers.payroll' => [
                'transport' => 'smtp',
                'scheme' => $scheme === 'none' ? 'smtp' : $scheme,
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'timeout' => 30,
                'auto_tls' => $scheme !== 'none',
            ],
            'mail.from.address' => $from,
            'mail.from.name' => $fromName ?: config('app.name'),
        ]);
        Mail::purge('payroll');
    }
}
