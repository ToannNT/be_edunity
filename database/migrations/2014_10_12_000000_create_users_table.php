<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('password');
            $table->string('provider', 30)->nullable();
            $table->string('provider_id')->nullable();
            $table->string('first_name', 50)->nullable();
            $table->string('last_name', 50)->nullable();
            $table->string('avatar')->nullable();
            $table->text('biography')->nullable();  // Thêm trường tiểu sử
            $table->string('phone_number', 20)->nullable();  // Thêm số điện thoại
            $table->string('address', 255)->nullable();  // Thêm địa chỉ
            $table->json('contact_info')->nullable();
            $table->enum('gender', ['male', 'female', 'unknown'])->default('unknown');
            $table->date('date_of_birth')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->string('reset_token')->nullable();
            $table->string('verification_token')->nullable();
            $table->enum('role', ['admin', 'instructor', 'student'])->default('student');
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->string('stripe_customer_id')->nullable();
            $table->softDeletes();
            $table->bigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
