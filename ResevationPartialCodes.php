<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('user_schedule_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->json('sessions');
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('user_schedule_daily');
    }
};



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserScheduleDaily extends Model {
    use HasFactory;

    protected $table = 'user_schedule_daily';

    protected $fillable = [
        'user_id',
        'date',
        'sessions'
    ];

    protected $casts = [
        'sessions' => 'array',
        'date' => 'date',
    ];
}




namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

class User extends Authenticatable {
    use HasFactory;

    public function dailySchedule() {
        $today = Carbon::today();
        $schedule = $this->hasOne(UserScheduleDaily::class)->whereDate('date', $today)->first();

        if (!$schedule) {
            // دریافت جلسات جدید برای امروز
            $sessions = $this->calculateDailySessions();

            // ذخیره در جدول
            $schedule = UserScheduleDaily::updateOrCreate(
                ['user_id' => $this->id],
                [ 'date' => $today,'sessions' => $sessions]
            );
        }

        return $schedule->sessions;
    }

    private function calculateDailySessions() {
        // این تابع را با توجه به نیاز خود پیاده‌سازی کنید
        return [
            ['title' => 'جلسه اول', 'time' => '09:00'],
            ['title' => 'جلسه دوم', 'time' => '11:00'],
        ];
    }
}
