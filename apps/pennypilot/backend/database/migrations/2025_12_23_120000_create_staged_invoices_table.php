<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create staged_invoices table for n8n/Gemini ingestion pipeline.
     * Invoices that can't be auto-linked to clients go here for manual review.
     */
    public function up(): void
    {
        Schema::create('staged_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('client_name', 255);
            $table->string('client_email', 255)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('description', 500)->nullable();
            $table->string('source', 20)->default('api'); // email, upload, scan, api
            $table->string('file_path', 500)->nullable();
            $table->json('raw_data')->nullable(); // Original Gemini extraction
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staged_invoices');
    }
};
