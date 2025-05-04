import Checkbox from '@/Components/Core/Checkbox';
import InputError from '@/Components/Core/InputError';
import InputLabel from '@/Components/Core/InputLabel';
import PrimaryButton from '@/Components/Core/PrimaryButton';
import TextInput from '@/Components/Core/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';


export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Log in" />

            <div className={"p-8"}>
                <div className='card bg-white shadow max-w-[420px] mx-auto'>
                    <div className='card-body'>
                        
                    {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
                )}
                    <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            Remember me
                        </span>
                    </label>
                </div>
                
                {/* Google login button */}
                <div className="mt-5 text-center">
                    <a 
                        // btn btn-google
                        href={route('auth.redirection', 'google')} 
                        className="w-full mt-4 py-2 px-4 rounded-md bg-blue-500 text-white hover:bg-blue-600">
                        <i className="fab fa-google mr-2"></i> Login with Google
                    </a>
                    {/* <a 
                    href="/auth/redirect/google"
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="..."
                    >
                    Login with Google
                    </a> */}
                </div>
                
                {/* Facebook login button */}
                <div className="mt-5 text-center">
                    <a 
                        // btn btn-facebook
                        href={route('auth.redirection', 'facebook')}
                        className="w-full mt-4 py-2 px-4 rounded-md bg-blue-700 text-white hover:bg-blue-800">
                        <i className="fab fa-facebook-f mr-2"></i> Login with Facebook
                    </a>
                    {/* <img src={ asset('assets/icons/facebook.jpeg') } /> */}
                </div>
                

                <div className="mt-4 flex items-center justify-end">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="link"
                        >
                            Forgot your password?
                        </Link>
                    )}

                    <PrimaryButton className={"btn btn-primary"} disabled={processing}>
                        Log in
                    </PrimaryButton>
                </div>
                    </form>
                </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
