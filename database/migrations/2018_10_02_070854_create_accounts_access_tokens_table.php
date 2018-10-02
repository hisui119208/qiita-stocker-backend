<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountsAccessTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts_access_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->string('access_token', 255);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique('account_id', 'uq_accounts_access_tokens_01');
            $table->unique('access_token', 'uq_accounts_access_tokens_02');
            $table->foreign('account_id', 'fk_accounts_access_tokens_01')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('accounts_access_tokens');
    }
}
