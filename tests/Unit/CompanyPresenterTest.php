<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\DataMapper\CompanySettings;
use App\Models\Company;
use App\Models\Presenters\CompanyPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CompanyPresenterTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testPrivateS3LogoUsesTemporaryUrl(): void
    {
        Carbon::setTestNow('2026-09-21 12:00:00 UTC');

        config([
            'filesystems.default' => 'private-logos',
            'filesystems.disks.private-logos' => [
                'driver' => 's3',
                'visibility' => 'private',
            ],
        ]);

        $logo_url = 'https://storage.example.com/bucket/company-key/logo.png';
        $temporary_url = $logo_url . '?signature=temporary';
        $storage = Mockery::mock();

        $storage->shouldReceive('url')
            ->once()
            ->with('company-key/logo.png')
            ->andReturn($logo_url);
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with('company-key/logo.png', Mockery::on(
                fn ($expires) => $expires->equalTo(now()->addHour())
            ))
            ->andReturn($temporary_url);

        Storage::shouldReceive('disk')
            ->once()
            ->with('private-logos')
            ->andReturn($storage);

        $this->assertSame($temporary_url, $this->presenter()->logo($this->settings($logo_url)));

    }

    public function testExternalLogoOnPrivateS3RemainsUnchanged(): void
    {
        config([
            'filesystems.default' => 'private-logos',
            'filesystems.disks.private-logos' => [
                'driver' => 's3',
                'visibility' => 'private',
            ],
        ]);

        $external_url = 'https://cdn.example.org/external-logo.png';
        $storage = Mockery::mock();

        $storage->shouldReceive('url')
            ->once()
            ->with('company-key/external-logo.png')
            ->andReturn('https://storage.example.com/bucket/company-key/external-logo.png');
        $storage->shouldNotReceive('temporaryUrl');

        Storage::shouldReceive('disk')
            ->once()
            ->with('private-logos')
            ->andReturn($storage);

        $this->assertSame($external_url, $this->presenter()->logo($this->settings($external_url)));
    }

    public function testPublicDiskLogoRemainsUnchanged(): void
    {
        config([
            'filesystems.default' => 'public-logos',
            'filesystems.disks.public-logos' => [
                'driver' => 's3',
                'visibility' => 'public',
            ],
        ]);

        $logo_url = 'https://cdn.example.com/company-key/logo.png';

        Storage::shouldReceive('disk')->never();

        $this->assertSame($logo_url, $this->presenter()->logo($this->settings($logo_url)));
    }

    private function presenter(): CompanyPresenter
    {
        $company = new Company();
        $company->company_key = 'company-key';

        return new CompanyPresenter($company);
    }

    private function settings(string $logo_url): CompanySettings
    {
        $settings = new CompanySettings();
        $settings->company_logo = $logo_url;

        return $settings;
    }
}
