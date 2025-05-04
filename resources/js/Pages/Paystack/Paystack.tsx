import React from "react";
import { Head } from "@inertiajs/react";

function Paystack() {
    // Get CSRF token from meta tag
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;

    return (
        <>
            <Head title="Make Payment" />
            <div className="max-w-md mx-auto mt-10 p-6 bg-white rounded shadow">
                <h1 className="text-2xl font-bold mb-4">Make Payment</h1>
                <form action="pay" method="POST" className="flex flex-col gap-4">
                    <input type="hidden" name="_token" value={csrfToken} />

                    <input
                        type="email"
                        name="email"
                        placeholder="Email Address"
                        required
                        className="border p-2 rounded"
                    />
                    <input
                        type="number"
                        name="amount"
                        placeholder="Enter Amount"
                        required
                        className="border p-2 rounded"
                    />
                    <button type="submit" className="bg-emerald-600 text-white py-2 px-4 rounded">
                        Submit
                    </button>
                </form>
            </div>
        </>
    );
}

export default Paystack;
