<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exchange_rates') || Schema::hasColumn('exchange_rates', 'pay_currency')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('exchange_rates_new', function (Blueprint $table) {
            $table->id();
            $table->string('pay_currency', 20);
            $table->string('receive_currency', 20);
            $table->unsignedInteger('pay_amount');
            $table->unsignedInteger('receive_amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pay_currency', 'receive_currency']);
        });

        $rows = DB::table('exchange_rates')->get();
        foreach ($rows as $row) {
            DB::table('exchange_rates_new')->insert([
                'id' => $row->id,
                'pay_currency' => $row->currency_a,
                'receive_currency' => $row->currency_b,
                'pay_amount' => $row->amount_a,
                'receive_amount' => $row->amount_b,
                'is_active' => $row->is_active,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('exchange_rates');
        Schema::rename('exchange_rates_new', 'exchange_rates');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (! Schema::hasTable('exchange_rates') || ! Schema::hasColumn('exchange_rates', 'pay_currency')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('exchange_rates_old', function (Blueprint $table) {
            $table->id();
            $table->string('currency_a', 20);
            $table->string('currency_b', 20);
            $table->unsignedInteger('amount_a');
            $table->unsignedInteger('amount_b');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['currency_a', 'currency_b']);
        });

        $rows = DB::table('exchange_rates')->get();
        foreach ($rows as $row) {
            DB::table('exchange_rates_old')->insert([
                'id' => $row->id,
                'currency_a' => $row->pay_currency,
                'currency_b' => $row->receive_currency,
                'amount_a' => $row->pay_amount,
                'amount_b' => $row->receive_amount,
                'is_active' => $row->is_active,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('exchange_rates');
        Schema::rename('exchange_rates_old', 'exchange_rates');

        Schema::enableForeignKeyConstraints();
    }
};
