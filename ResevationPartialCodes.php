<?php

class ReservationController
{
    private $reservationService;
    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    public function getAvailableTimes(AvailableTimeRequest $request)
    {
        return $this->reservationService->getAvailableTimes($request);
    }
}

class Meeting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'date' => 'date:Y-m-d',
        'status' => MeetingStatus::class,
    ];

}

enum MeetingStatus: int {
    case Pending = 0;
    case Approved = 1;
    case Reject = 2;
    case Public = 3;

    public function toReadableStatus(): string
    {
        return match ($this) {
            self::Approved => 'approved',
            self::Pending => 'reserved',
            self::Reject => 'reject',
            self::Public => 'public',
            default => 'pending',
        };
    }

}

class ReservationService
{
     public function getAvailableTimes($request)
    {
        $teacher = Cache::remember("teacher_subject_{$request->subject}", 24 * 60, function () use ($request) {
            return $this->getTeacherBySubject($request->subject);
        });

        if (!$teacher) {
            return response()->json(['error' => 'No teacher found for this subject'], 404);
        }
        $meetingTimes = Cache::remember("meeting_times_{$request->date}", 60, function () use ($request) {
            return $this->getMeetingTimes($request->date);
        });

        return Cache::remember("time_slots_{$request->date}", 60, function () use ($meetingTimes) {
            return $this->generateTimeSlots($meetingTimes);
        });
    }

    private function getTeacherBySubject($subject)
    {
        return TeacherSubject::where('subject', $subject)->first();
    }

    private function getMeetingTimes($date)
    {
        return Meeting::whereDate('date', $date)
            ->whereIn('status', [MeetingStatus::Approved->value, MeetingStatus::Pending->value])
            ->select('start_time', 'status')
            ->get()
            ->mapWithKeys(function ($meeting) {
                $startTime = $meeting->start_time->format('H:i');
                return [$startTime => $meeting->status->value];
            });
    }

    private function generateTimeSlots($meetingTimes):array
    {
        $timeSlots = collect();
        $startTime = Carbon::createFromTime(TimeReserveMeeting::START_HOUR_DAY->value, 0);
        $endTime = Carbon::createFromTime(TimeReserveMeeting::END_HOUR_DAY->value, 0);
        $interval = TimeReserveMeeting::PER_MINUTE->value;

        while ($startTime->lessThan($endTime)) {
            $formattedStart = $startTime->format('H:i');
            $statusValue = $meetingTimes[$formattedStart] ?? null;

            $finalStatus = $statusValue !== null
                ? MeetingStatus::from((int)$statusValue)->toReadableStatus()
                : 'pending';

            if ($startTime->greaterThanOrEqualTo(now())) {
                $timeSlots->push([
                    'time' => $formattedStart,
                    'status' => $finalStatus,
                ]);
            }

            $startTime->addMinutes($interval);
        }

        return $timeSlots->toArray();
    }
}
