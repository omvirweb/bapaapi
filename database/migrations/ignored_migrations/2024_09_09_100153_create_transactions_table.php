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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            // $table->string('party_name');
            $table->unsignedBigInteger('party_id');
            // $table->unsignedBigInteger('item');
            $table->string('item');
            $table->decimal('gross', 10, 3)->default(0)->nullable();
            $table->decimal('weight', 10, 3)->default(0)->nullable();
            $table->decimal('less', 10, 3)->default(0)->nullable();
            $table->decimal('add', 10, 3)->default(0)->nullable();
            $table->decimal('net_wt', 10, 3)->default(0)->nullable();
            $table->decimal('touch', 5, 2)->default(0)->nullable();
            $table->decimal('wastage', 10, 2)->default(0)->nullable();
            $table->decimal('fine', 10, 3)->default(0)->nullable();
            $table->date('date');
            $table->string('note')->nullable();
            $table->string('type');
            $table->boolean('is_checked')->default(0); // Store Notes checkbox value (0 or 1)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
