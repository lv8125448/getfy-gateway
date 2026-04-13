<?php

namespace Tests\Feature;

use App\Mail\KycApprovedMail;
use App\Mail\KycRejectedMail;
use App\Mail\KycSubmittedAdminMail;
use App\Mail\WelcomeInfoprodutorMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformTransactionalEmailTest extends TestCase
{
    public function test_registration_sends_welcome_email(): void
    {
        Mail::fake();

        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PLATFORM_ADMIN,
            'tenant_id' => null,
        ]);

        $payload = [
            'person_type' => 'pf',
            'name' => 'Vendedor Mail',
            'email' => 'vendedor-mail@example.com',
            'birth_date' => '1990-05-15',
            'document' => '52998224725',
            'company_name' => null,
            'legal_representative_cpf' => null,
            'address_zip' => '01310100',
            'address_street' => 'Av Paulista',
            'address_number' => '1000',
            'address_complement' => '',
            'address_neighborhood' => 'Bela Vista',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
            'monthly_revenue_range' => 'up_to_10k',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $this->post('/cadastro', $payload)->assertRedirect('/dashboard');

        Mail::assertSent(WelcomeInfoprodutorMail::class, function (WelcomeInfoprodutorMail $mail) {
            return $mail->user->email === 'vendedor-mail@example.com';
        });
    }

    public function test_kyc_submit_sends_admin_notification_when_emails_configured(): void
    {
        Mail::fake();

        Setting::set('kyc_notification_emails', 'kyc-admin@example.com', null);

        $seller = User::query()->create([
            'name' => 'Seller KYC',
            'email' => 'seller-kyc@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_INFOPRODUTOR,
            'person_type' => 'pf',
            'document' => '52998224725',
            'kyc_status' => User::KYC_NOT_SUBMITTED,
            'birth_date' => '1990-01-01',
            'address_zip' => '01310100',
            'address_street' => 'Rua A',
            'address_number' => '1',
            'address_neighborhood' => 'Centro',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
            'monthly_revenue_range' => 'up_to_10k',
        ]);
        $seller->update(['tenant_id' => $seller->id]);

        $front = UploadedFile::fake()->create('rg-f.jpg', 50, 'image/jpeg');
        $back = UploadedFile::fake()->create('rg-v.jpg', 50, 'image/jpeg');

        $this->actingAs($seller)->post('/kyc', [
            'rg_front' => $front,
            'rg_back' => $back,
        ])->assertRedirect();

        Mail::assertSent(KycSubmittedAdminMail::class, function (KycSubmittedAdminMail $mail) use ($seller) {
            return $mail->merchant->is($seller);
        });
    }

    public function test_kyc_approve_sends_email_to_merchant(): void
    {
        Mail::fake();

        $admin = User::query()->create([
            'name' => 'Plat Admin',
            'email' => 'plat@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PLATFORM_ADMIN,
            'tenant_id' => null,
        ]);

        $merchant = User::query()->create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_INFOPRODUTOR,
            'person_type' => 'pf',
            'document' => '52998224725',
            'kyc_status' => User::KYC_PENDING_REVIEW,
            'birth_date' => '1990-01-01',
            'address_zip' => '01310100',
            'address_street' => 'Rua A',
            'address_number' => '1',
            'address_neighborhood' => 'Centro',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
            'monthly_revenue_range' => 'up_to_10k',
        ]);
        $merchant->update(['tenant_id' => $merchant->id]);

        $this->actingAs($admin)->post(
            route('plataforma.kyc.approve', ['user' => $merchant->id])
        )->assertRedirect();

        Mail::assertSent(KycApprovedMail::class, function (KycApprovedMail $mail) use ($merchant) {
            return $mail->merchant->is($merchant);
        });
    }

    public function test_kyc_reject_sends_email_to_merchant(): void
    {
        Mail::fake();

        $admin = User::query()->create([
            'name' => 'Plat Admin',
            'email' => 'plat2@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PLATFORM_ADMIN,
            'tenant_id' => null,
        ]);

        $merchant = User::query()->create([
            'name' => 'Merchant Rej',
            'email' => 'merchant-rej@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_INFOPRODUTOR,
            'person_type' => 'pf',
            'document' => '52998224725',
            'kyc_status' => User::KYC_PENDING_REVIEW,
            'birth_date' => '1990-01-01',
            'address_zip' => '01310100',
            'address_street' => 'Rua A',
            'address_number' => '1',
            'address_neighborhood' => 'Centro',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
            'monthly_revenue_range' => 'up_to_10k',
        ]);
        $merchant->update(['tenant_id' => $merchant->id]);

        $this->actingAs($admin)->post(
            route('plataforma.kyc.reject', ['user' => $merchant->id]),
            ['reason' => 'Documento ilegível.']
        )->assertRedirect();

        Mail::assertSent(KycRejectedMail::class, function (KycRejectedMail $mail) use ($merchant) {
            return $mail->merchant->is($merchant) && str_contains($mail->rejectionReason, 'ilegível');
        });
    }
}
