import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { index } from '@/routes/otpz';

type VerifyForm = {
    code: string;
};

type Props = {
    status?: string;
    email: string;
    url: string;
};

export default function OtpzVerify({ status, email, url }: Props) {
    const { data, setData, post, processing, errors, reset } =
        useForm<VerifyForm>({
            code: '',
        });
    const [displayValue, setDisplayValue] = useState('');

    const formatValue = (value: string): string => {
        const alphanumeric = value.replace(/[^a-zA-Z0-9]/g, '');

        if (alphanumeric.length <= 5) {
            return alphanumeric;
        }

        const firstPart = alphanumeric.substring(0, 5);
        const secondPart = alphanumeric.substring(5, 10);

        return `${firstPart}-${secondPart}`;
    };

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const formattedValue = formatValue(e.target.value);
        setDisplayValue(formattedValue);
        setData('code', formattedValue.replace(/-/g, ''));
    };

    useEffect(() => {
        if (data.code === '') {
            setDisplayValue('');
        }
    }, [data.code]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(url, {
            onFinish: () => {
                reset('code');
                setDisplayValue('');
            },
        });
    };

    return (
        <AuthLayout
            title="Enter your code"
            description={`Enter the login code sent to ${email}. The code is case insensitive.`}
        >
            <Head title="Verify code" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="code">Login code</Label>
                        <Input
                            className="text-center uppercase placeholder:lowercase"
                            id="code"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            maxLength={11}
                            autoComplete="off"
                            value={displayValue}
                            onChange={handleInputChange}
                            placeholder="xxxxx-xxxxx"
                        />
                        <InputError message={errors.code} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-4 w-full"
                        tabIndex={4}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Submit code
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    Did not receive it?{' '}
                    <TextLink href={index()} tabIndex={5}>
                        Request a new code
                    </TextLink>
                </div>
            </form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
