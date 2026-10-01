<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The provider directory: the only source of truth the agent may quote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->char('country_code', 2)->index();
            $table->string('city_en')->index();
            $table->string('city_ar');
            $table->string('address_en');
            $table->string('phone');
            $table->boolean('has_emergency')->default(false)->index();
            $table->string('emergency_phone')->nullable();
            $table->string('accreditation')->nullable();
            $table->json('languages');
            $table->boolean('international_patient_desk')->default(false);
            $table->timestamps();
        });

        Schema::create('hospital_specialty', function (Blueprint $table) {
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('specialty');
            $table->primary(['hospital_id', 'specialty']);
            $table->index('specialty');
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('specialty')->index();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('focus_en');
            $table->string('focus_ar');
            $table->unsignedTinyInteger('years_experience');
            $table->json('languages');
            $table->unsignedInteger('consultation_fee_usd');
            $table->boolean('offers_second_opinion')->default(false);
            $table->boolean('offers_telemedicine')->default(false);
            $table->decimal('rating', 2, 1);
            $table->date('next_available_on');
            $table->timestamps();

            $table->index(['specialty', 'offers_second_opinion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('hospital_specialty');
        Schema::dropIfExists('hospitals');
    }
};
