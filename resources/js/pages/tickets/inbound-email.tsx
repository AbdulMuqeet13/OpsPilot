import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { AppHead } from '@/components/app-head';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardBody, CardFooter, CardHeader } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field-error';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/inbound-email';
import { index as ticketsIndex } from '@/routes/tickets';

const sampleEmails = [
    {
        label: 'Late delivery (English)',
        raw: `From: Priya Raman <priya.raman@example.com>
To: help@oaklinehome.example
Subject: Where is my dining table?? Order OH-48213
Date: Wed, 29 Oct 2026 08:14:00 +0000
Content-Type: text/plain; charset=UTF-8

Hello,

I ordered the Fenwick oak dining table three weeks ago and the website said delivery in 7-10 days. It still hasn't arrived and nobody has emailed me. We have family coming on Saturday and I really need it before then.

Can someone tell me what is going on?

Priya`,
    },
    {
        label: 'Damaged item (Spanish)',
        raw: `From: "Javier Morales" <javier.morales@example.net>
Subject: Silla rota al llegar
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

Buenas tardes,

Ayer recib=C3=AD las cuatro sillas Aldo pero una de ellas tiene la pata trasera partida. Adjunto fotos. =C2=BFPueden enviarme una nueva o tengo que devolverla?

Gracias,
Javier`,
    },
];

export default function InboundEmail() {
    const [raw, setRaw] = useState('');

    return (
        <>
            <AppHead title="Paste an email" />
            <PageHeader
                title="Paste an inbound email"
                description="This simulates your support inbox: paste a raw email, headers included, and it becomes a ticket that the AI triages straight away."
                actions={
                    <Link
                        href={ticketsIndex()}
                        className="text-sm text-slate-500 hover:text-slate-800"
                    >
                        Back to tickets
                    </Link>
                }
            />

            <Form {...store.form()}>
                {({ errors, processing }) => (
                    <Card>
                        <CardHeader
                            title="Raw email"
                            description="Needs at least a From: header, a blank line, then the message body."
                            action={
                                <div className="flex flex-wrap gap-2">
                                    {sampleEmails.map((sample) => (
                                        <Button
                                            key={sample.label}
                                            size="sm"
                                            variant="secondary"
                                            onClick={() => setRaw(sample.raw)}
                                        >
                                            Use sample: {sample.label}
                                        </Button>
                                    ))}
                                </div>
                            }
                        />
                        <CardBody>
                            <label htmlFor="raw_email" className="sr-only">
                                Raw email
                            </label>
                            <Textarea
                                id="raw_email"
                                name="raw_email"
                                rows={16}
                                value={raw}
                                onChange={(event) => setRaw(event.target.value)}
                                className="font-mono text-xs"
                                placeholder={
                                    'From: Jane Doe <jane@example.com>\nSubject: Question about my order\n\nHi there, …'
                                }
                                aria-invalid={!!errors.raw_email}
                            />
                            <FieldError message={errors.raw_email} />
                        </CardBody>
                        <CardFooter>
                            <Button
                                type="submit"
                                loading={processing}
                                disabled={!raw.trim()}
                            >
                                Create ticket
                            </Button>
                        </CardFooter>
                    </Card>
                )}
            </Form>
        </>
    );
}
