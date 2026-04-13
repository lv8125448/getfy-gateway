<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('document');
            }
            if (! Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name', 255)->nullable()->after('birth_date');
            }
            if (! Schema::hasColumn('users', 'legal_representative_cpf')) {
                $table->string('legal_representative_cpf', 14)->nullable()->after('company_name');
            }
            if (! Schema::hasColumn('users', 'address_zip')) {
                $table->string('address_zip', 16)->nullable()->after('legal_representative_cpf');
            }
            if (! Schema::hasColumn('users', 'address_street')) {
                $table->string('address_street', 255)->nullable()->after('address_zip');
            }
            if (! Schema::hasColumn('users', 'address_number')) {
                $table->string('address_number', 32)->nullable()->after('address_street');
            }
            if (! Schema::hasColumn('users', 'address_complement')) {
                $table->string('address_complement', 120)->nullable()->after('address_number');
            }
            if (! Schema::hasColumn('users', 'address_neighborhood')) {
                $table->string('address_neighborhood', 120)->nullable()->after('address_complement');
            }
            if (! Schema::hasColumn('users', 'address_city')) {
                $table->string('address_city', 120)->nullable()->after('address_neighborhood');
            }
            if (! Schema::hasColumn('users', 'address_state')) {
                $table->string('address_state', 2)->nullable()->after('address_city');
            }
            if (! Schema::hasColumn('users', 'monthly_revenue_range')) {
                $table->string('monthly_revenue_range', 32)->nullable()->after('address_state');
            }
            if (! Schema::hasColumn('users', 'kyc_status')) {
                $table->string('kyc_status', 32)->default('not_submitted')->after('monthly_revenue_range');
            }
            if (! Schema::hasColumn('users', 'kyc_rejection_reason')) {
                $table->text('kyc_rejection_reason')->nullable()->after('kyc_status');
            }
            if (! Schema::hasColumn('users', 'kyc_reviewed_at')) {
                $table->timestamp('kyc_reviewed_at')->nullable()->after('kyc_rejection_reason');
            }
            if (! Schema::hasColumn('users', 'kyc_reviewed_by')) {
                $table->foreignId('kyc_reviewed_by')->nullable()->after('kyc_reviewed_at')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('kyc_documents')) {
            Schema::create('kyc_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind', 32);
                $table->string('disk_path', 512);
                $table->string('original_mime', 128)->nullable();
                $table->unsignedInteger('size_bytes')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'kind']);
            });
        }

        if (Schema::hasColumn('users', 'kyc_status')) {
            DB::table('users')->where('role', 'infoprodutor')->update(['kyc_status' => 'approved']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');

        if (Schema::hasColumn('users', 'kyc_reviewed_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('kyc_reviewed_by');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'birth_date', 'company_name', 'legal_representative_cpf',
                'address_zip', 'address_street', 'address_number', 'address_complement',
                'address_neighborhood', 'address_city', 'address_state',
                'monthly_revenue_range', 'kyc_status', 'kyc_rejection_reason',
                'kyc_reviewed_at',
            ];
            $existing = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('users', $c)));
            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
