<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role', 20)->default('student')->after('email')->index();
        });

        Schema::create('event_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->string('slug', 100)->unique();
            $t->timestamps();
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('category_id')->constrained('event_categories');
            $t->string('title', 150);
            $t->text('description');
            $t->string('location', 150);
            $t->dateTime('starts_at');
            $t->dateTime('registration_closes_at');
            $t->dateTime('cancellation_closes_at');
            $t->unsignedInteger('capacity');
            $t->string('status', 20)->default('draft'); // draft | published | closed
            $t->timestamps();
            $t->index(['status', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20); // confirmed | waitlisted | cancelled
            // 1 while the registration is active, NULL once cancelled.
            // UNIQUE(event_id,user_id,active) => one active registration per student per event
            // (NULLs are never equal in a unique index, so cancelled rows never collide).
            $t->unsignedTinyInteger('active')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->unique(['event_id', 'user_id', 'active'], 'uniq_active_registration');
            $t->index(['event_id', 'status', 'id']);
        });

        Schema::create('event_attendance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('registration_id')->unique()->constrained('event_registrations')->cascadeOnDelete();
            $t->foreignId('marked_by')->constrained('users');
            $t->timestamp('checked_in_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendance');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_categories');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
