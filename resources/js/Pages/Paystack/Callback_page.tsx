import React from "react";
import { Head } from "@inertiajs/react";

type CallbackPageProps = {
    data: {
        status: string;
        reference: string;
        amount: number;
        fees: number;
        customer: {
            email: string;
        };
    };
};

function CallbackPage({ data }: CallbackPageProps) {
    return (
        <>
            <Head title="Payment Callback" />
            <div className="max-w-md mx-auto mt-10 p-6 bg-white rounded shadow text-center">
                <h1 className="text-2xl font-bold mb-6">Payment Summary</h1>
                <table className="table-auto mx-auto text-left">
                    <tbody>
                        <tr><td className="font-semibold pr-4">Status:</td><td>{data.status}</td></tr>
                        <tr><td className="font-semibold pr-4">Reference:</td><td>{data.reference}</td></tr>
                        <tr><td className="font-semibold pr-4">Amount:</td><td>₦{(data.amount / 100).toFixed(2)}</td></tr>
                        <tr><td className="font-semibold pr-4">Fees:</td><td>₦{(data.fees / 100).toFixed(2)}</td></tr>
                        <tr><td className="font-semibold pr-4">Email:</td><td>{data.customer.email}</td></tr>
                    </tbody>
                </table>
            </div>
        </>
    );
}

export default CallbackPage;
