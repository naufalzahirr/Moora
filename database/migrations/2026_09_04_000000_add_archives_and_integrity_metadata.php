<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table): void {
            $table->string('source_path')->nullable()->after('source_file');
            $table->string('source_sha256', 64)->nullable()->after('source_path');
            $table->unsignedBigInteger('source_size')->nullable()->after('source_sha256');
            $table->string('source_mime')->nullable()->after('source_size');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->string('storage_path')->nullable()->after('document_name');
            $table->string('sha256', 64)->nullable()->after('storage_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('sha256');
            $table->timestamp('archived_at')->nullable()->after('generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn(['storage_path', 'sha256', 'file_size', 'archived_at']);
        });

        Schema::table('periods', function (Blueprint $table): void {
            $table->dropColumn(['source_path', 'source_sha256', 'source_size', 'source_mime']);
        });
    }
};
