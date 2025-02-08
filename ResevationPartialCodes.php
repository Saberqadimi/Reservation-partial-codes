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

        $approvedMeetings = $this->getApprovedMeetingTimes($request->date);

        return $this->generateTimeSlots($approvedMeetings);
    }

    private function getTeacherBySubject($subject)
    {
        return TeacherSubject::where('subject', $subject)->first();
    }

    private function getApprovedMeetingTimes($date)
    {
        return Meeting::where('date', $date)
            ->where('status', 'approved')
            ->select('start_time')
            ->get()
            ->map(fn($meeting) => Carbon::parse($meeting->start_time)->format('H:i'));
    }


    private function generateTimeSlots($approvedMeetings)
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
            $formattedEnd = $nextTime->format('H:i');
            $isApproved = $approvedMeetings->contains($formattedStart);

            $timeSlots[] = [
                'time' => "$formattedStart - $formattedEnd",
                'status' => $isApproved ? 'approved' : 'pending'
            ];

            $startTime = $nextTime;
        }

        return $timeSlots;
    }
}
