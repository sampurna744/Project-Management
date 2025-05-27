<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Replace these placeholders with your actual sandbox credentials
$clientId = 'AW7Va3T7aJJzbdEdc7-PoAM8UumMwBGknHhzBfwv1UCXQDvkfO8Up3F6c-UZTj_M7zcyhwJjRhpS_dr7';
$clientSecret = 'EDVQS-C8iaUU3JwQHplLwQHsP0gOnSc_LVZ4-TIY4cLM1Q5YksmT7TNVH-bAADT4I3o-2eTYlVhZycZA';
$orderId = $_GET['order_id']; // Assuming 'order_id' is passed from the previous page


function getPayPalAccessToken($clientId, $clientSecret, $apiEndpoint) {
    $ch = curl_init($apiEndpoint . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}
// Include connection file to the database
include("connection/connection.php");

// Function to make API call to PayPal
function makePayPalApiCall($apiEndpoint, $headers, $body) {
    $ch = curl_init($apiEndpoint . '/v1/payments/payment');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Set up the payment amount and currency
$amount = $_GET['total_price']; // Amount in pence or smallest currency unit
$currency = 'GBP'; // Currency code for pound sterling

// Set up your product details
$productName = "";
$productDescription = "Purchased " . $_GET["total_products"] . " Products From Cleckfax Trader Hub";

// Set up PayPal API endpoints for sandbox
$apiEndpoint = 'https://api.sandbox.paypal.com';
$redirectUrl = 'http://localhost/merger/Cleckfax-Traders-Hub/thankyou.php/thankyou.php'; 

// Set up PayPal API request headers
$accessToken = getPayPalAccessToken($clientId, $clientSecret, $apiEndpoint);
if (!$accessToken) {
    echo "Failed to authenticate with PayPal. Please try again later.";
    exit;
}
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
];
$productNames = isset($_GET['products']) ? $_GET['products'] : '';
$slotDate = isset($_GET['slot_date']) ? $_GET['slot_date'] : '';
$slotTime = isset($_GET['slot_time']) ? $_GET['slot_time'] : '';
// Set up PayPal API request body
$body = [
    'intent' => 'sale',
    'payer' => [
        'payment_method' => 'paypal'
    ],
    'transactions' => [
        [
            'amount' => [
                'total' => number_format($amount / 100, 2), //need to remove this in demo. 
                'currency' => $currency
            ],
            'description' => $productDescription
        ]
    ],
    
    'redirect_urls' => [
    'return_url' => $redirectUrl . '?success=true' . 
        '&total_price=' . urlencode($_GET['total_price']) . 
        '&total_products=' . urlencode($_GET['total_products']) . 
        '&order_id=' . urlencode($_GET['order_id']) . 
        '&customer_id=' . urlencode($_GET['customer_id']),
    'cancel_url' => $redirectUrl . '?success=false'
    ]
];

// Make API call to PayPal
$payment = makePayPalApiCall($apiEndpoint, $headers, $body);
error_log(print_r($payment, true));
// Redirect user to PayPal for payment authorization
if(isset($payment['id'])) {
    foreach ($payment['links'] as $link) {
        if ($link['rel'] === 'approval_url') {
            $redirectUrl = $link['href'];
            echo "<script>
                window.open('$redirectUrl', '_blank');
                
            </script>";
            exit;
        }
    }
} else {
    echo "Payment failed. Please try again later.";
}
?>

