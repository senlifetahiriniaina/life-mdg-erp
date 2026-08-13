<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add mfa_method column to users if missing
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'mfa_method')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('mfa_method', 30)->nullable();
            });
        }

        // Create core_security_incidents table
        if (!Schema::hasTable('core_security_incidents')) {
            Schema::create('core_security_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description');
                $table->string('severity', 20)->default('medium');
                $table->string('status', 20)->default('open');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution')->nullable();
                $table->text('prevention')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('core_security_incidents', function (Blueprint $table) {
                if (!Schema::hasColumn('core_security_incidents', 'title')) {
                    $table->string('title');
                }
                if (!Schema::hasColumn('core_security_incidents', 'description')) {
                    $table->text('description');
                }
                if (!Schema::hasColumn('core_security_incidents', 'severity')) {
                    $table->string('severity', 20)->default('medium');
                }
                if (!Schema::hasColumn('core_security_incidents', 'status')) {
                    $table->string('status', 20)->default('open');
                }
                if (!Schema::hasColumn('core_security_incidents', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (!Schema::hasColumn('core_security_incidents', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable();
                }
                if (!Schema::hasColumn('core_security_incidents', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable();
                }
                if (!Schema::hasColumn('core_security_incidents', 'resolution')) {
                    $table->text('resolution')->nullable();
                }
                if (!Schema::hasColumn('core_security_incidents', 'prevention')) {
                    $table->text('prevention')->nullable();
                }
            });
        }

        // Create core_security_incident_communications table
        if (!Schema::hasTable('core_security_incident_communications')) {
            Schema::create('core_security_incident_communications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('incident_id');
                $table->string('type', 50);
                $table->text('message');
                $table->timestamps();

                $table->foreign('incident_id')
                      ->references('id')
                      ->on('core_security_incidents')
                      ->onDelete('cascade');
            });
        } else {
            Schema::table('core_security_incident_communications', function (Blueprint $table) {
                if (!Schema::hasColumn('core_security_incident_communications', 'incident_id')) {
                    $table->unsignedBigInteger('incident_id');
                }
                if (!Schema::hasColumn('core_security_incident_communications', 'type')) {
                    $table->string('type', 50);
                }
                if (!Schema::hasColumn('core_security_incident_communications', 'message')) {
                    $table->text('message');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_security_incident_communications');
        Schema::dropIfExists('core_security_incidents');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'mfa_method')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('mfa_method');
            });
        }
    }
};
