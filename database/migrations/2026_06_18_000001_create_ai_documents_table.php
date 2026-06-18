<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('filename');
            $table->string('path')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('status')->default('Pending');
            $table->integer('inserted_count')->default(0);
            $table->string('external_id')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ai_documents');
    }
};
