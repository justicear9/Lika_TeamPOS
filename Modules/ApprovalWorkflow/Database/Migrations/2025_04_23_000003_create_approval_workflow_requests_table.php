<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('approval_workflow_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('transaction_id');
            $table->unsignedInteger('rule_id');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedInteger('resolved_by')->nullable();
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->unique('transaction_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('approval_workflow_requests');
    }
};
