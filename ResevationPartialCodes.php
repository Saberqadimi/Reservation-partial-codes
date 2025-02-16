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

class ReservationService
{
    public function getAvailableTimes($request)
    {
        $teacher = $this->getTeacherBySubject($request->subject);
        if (!$teacher) {
            return response()->json(['error' => 'No teacher found for this subject'], 404);
        }

        $meetingTimes = $this->getMeetingTimes($request->date);

        return $this->generateTimeSlots($meetingTimes);
    }

    private function getTeacherBySubject($subject)
    {
        return TeacherSubject::where('subject', $subject)->first();
    }

    private function getMeetingTimes($date)
    {
        return Meeting::where('date', $date)
            ->whereIn('status', ['approved', 'pending'])
            ->select('start_time', 'status')
            ->get()
            ->mapWithKeys(function ($meeting) {
                return [Carbon::parse($meeting->start_time)->format('H:i') => $meeting->status];
            });
    }

    private function generateTimeSlots($meetingTimes)
    {
        $startTime = Carbon::createFromTime(TimeReserveMeeting::START_HOUR_DAY->value, 0);
        $endTime = Carbon::createFromTime(TimeReserveMeeting::END_HOUR_DAY->value, 0);
        $timeSlots = [];

        while ($startTime->lessThan($endTime)) {
            $nextTime = $startTime->copy()->addMinutes(TimeReserveMeeting::PER_MINUTE->value);

            if ($startTime->lessThan(now())) {
                $startTime = $nextTime;
                continue;
            }

            $formattedStart = $startTime->format('H:i');
            $status = $meetingTimes[$formattedStart] ?? null;

            if ($status === 'approved') {
                $finalStatus = 'approved';
            } elseif ($status === 'pending') {
                $finalStatus = 'reserved';
            } else {
                $finalStatus = 'pending';
            }

            $timeSlots[] = [
                'time' => $formattedStart,
                'status' => $finalStatus
            ];

            $startTime = $nextTime;
        }

        return $timeSlots;
    }
}
