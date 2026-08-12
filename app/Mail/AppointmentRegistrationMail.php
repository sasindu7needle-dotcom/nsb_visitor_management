<?php

namespace App\Mail;

use App\Models\VisitorAppointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VisitorAppointment $appointment,
        private readonly string $registrationToken,
    ) {
    }

    public function build(): static
    {
        return $this
            ->subject('Complete your visitor registration - '.$this->appointment->reference)
            ->view('emails.appointments.registration')
            ->with([
                'registrationUrl' => route('visitor.appointments.start', [
                    'appointment' => $this->appointment,
                    'token' => $this->registrationToken,
                ]),
            ]);
    }
}
