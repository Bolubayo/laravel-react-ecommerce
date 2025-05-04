import React, { useEffect, useState } from "react";
import { Head } from "@inertiajs/react";

declare global {
    interface Window {
        FlutterwaveCheckout: any;
    }
}

type FlutterwaveProps = {
    flutterwavePublicKey: string;
};

const Flutterwave: React.FC<FlutterwaveProps> = ({ flutterwavePublicKey }) => {
    const [name, setName] = useState("");
    const [email, setEmail] = useState("");
    const [amount, setAmount] = useState<number | "">("");
    const [phone, setPhone] = useState("");

    // Load Flutterwave script
    useEffect(() => {
        console.log("Received Flutterwave Public Key:", flutterwavePublicKey);
        const script = document.createElement("script");
        script.src = "https://checkout.flutterwave.com/v3.js";
        script.async = true;
        document.body.appendChild(script);

        return () => {
            document.body.removeChild(script);
        };
    }, []);

    const makePayment = () => {
        if (!email || !name || !amount || !phone) {
            alert("Please fill all fields");
            return;
        }

        const txRef = `TXREF_${Date.now()}`;

        window.FlutterwaveCheckout({
            public_key: flutterwavePublicKey,
            tx_ref: txRef,
            amount: Number(amount),
            currency: 'NGN',
            payment_options: 'card, mobilemoneyghana, ussd',
            customer: {
                email,
                phone_number: phone,
                name,
            },
            callback: function (data: any) {
                console.log("Payment successful:", data);
                // Optional: Send data to Laravel via fetch or Inertia visit
            },
            onclose: function () {
                console.log("Payment popup closed");
            },
            customizations: {
                title: 'The Titanic Store',
                description: 'Payment for an awesome cruise',
                logo: 'https://www.logolynx.com/images/logolynx/22/2239ca38f5505fbfce7e55bbc0604386.jpeg',
            },
        });
    };

    return (
        <>
            <Head title="Make Flutterwave Payment" />
            <div className="max-w-md mx-auto mt-10 p-6 bg-white rounded shadow">
                <h1 className="text-2xl font-bold mb-4">Make Flutterwave Payment</h1>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        makePayment();
                    }}
                    className="flex flex-col gap-4"
                >
                    <input
                        type="text"
                        placeholder="Enter Fullname"
                        required
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        className="border p-2 rounded"
                    />
                    <input
                        type="email"
                        placeholder="Email Address"
                        required
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className="border p-2 rounded"
                    />
                    <input
                        type="number"
                        placeholder="Enter Amount"
                        required
                        value={amount}
                        onChange={(e) => setAmount(Number(e.target.value))}
                        className="border p-2 rounded"
                    />
                    <input
                        type="tel"
                        placeholder="Enter Phone Number"
                        required
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        className="border p-2 rounded"
                    />
                    <button
                        type="submit"
                        className="bg-emerald-600 text-white py-2 px-4 rounded hover:bg-emerald-700"
                    >
                        Pay Now
                    </button>
                </form>
            </div>
        </>
    );
};

export default Flutterwave;
