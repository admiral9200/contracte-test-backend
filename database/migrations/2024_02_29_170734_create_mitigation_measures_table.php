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
        Schema::create('mitigation_measures', function (Blueprint $table) {
            $table->increments('id');
            $table->string('document_analytics_id');
            $table->text('mitigation_measure');
            $table->string('thumbs');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitigation_measures');
    }
};
