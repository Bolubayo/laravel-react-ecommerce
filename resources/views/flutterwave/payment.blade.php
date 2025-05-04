<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flutterwave Payment Page For Laravel 12</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  </head>
  <body>
    <div class="container">
        <div class="header mt-2 px-5 text-center bg-primary py-5 text-white">
            <h1>Pay for services</h1>    
        </div>
        <div class="main">
            <form id="makePaymentForm">
                @csrf
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" name="name" id="name" class="form-control" required  placeholder="Enter full name">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required placeholder="Enter email">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="number">Phone Number</label>
                            <input type="number" name="number" id="number" class="form-control" required placeholder="Enter number">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="number" name="amount" id="amount" class="form-control" required placeholder="Enter amount">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Pay Now</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js" integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+" crossorigin="anonymous"></script>

    <script>
        $(function () {
            $("#makePaymentForm").submit(function (e) {
                e.preventDefault();
                var name = $("#name").val();
                var email = $("#email").val();
                var phone = $("#number").val();
                var amount = $("#amount").val();
                makePayment(amount,email,phone,name);
            });
        });
        
        function makePayment(amount,email,phone_number,name) {
            FlutterwaveCheckout({
                public_key: "{{ $flutterwavePublicKey }}",
                tx_ref: "RX1_" + Date.now() + "_" + Math.floor(Math.random() * 1000),
                amount,
                currency: 'NGN',
                country: 'NG',
                payment_options: 'card,banktransfer,ussd',
                customer: {
                    email,
                    phone_number,
                    name,
                },
                callback: function(data) {
                    var transaction_id = data.transaction_id;
                    var _token = $("input[name='_token']").val();
                    $.ajax({
                        type: "POST",
                        url: "{{ route('flutterwave.verify') }}",
                        data: {
                            transaction_id,
                            _token
                        },
                        success: function (response) {
                            console.log(response);
                        }
                    });
                },
                onclose: function() {
                     if (response.status === 'success') {
                        alert('Payment successful! 🎉');
                        window.location.href = "{{ route('dashboard') }}"; // redirect to home or anywhere
                    } else {
                        alert('Payment failed. Please try again.');
                    }
                },
                customizations: {
                    title: 'Daniel Store',
                    description: 'Payment for an awesome cruise',
                    logo: 'https://www.thecable.ng/wp-content/uploads/2022/07/1200px-Flutterwave_Logo.png',
                },
            });
        }
    </script>
  </body>
</html>
