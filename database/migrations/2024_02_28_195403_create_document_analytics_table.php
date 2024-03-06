<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_analytics', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('file_name');
            $table->string('category');
            $table->text('risky_sentence');
            $table->text('risk_definition');
            $table->string('probability');
            $table->string('impact_on_client');
            $table->text('mitigation_measure');
            $table->string('probability_after_mitigation');
            $table->string('average_risk_score');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_analytics');
    }
};
