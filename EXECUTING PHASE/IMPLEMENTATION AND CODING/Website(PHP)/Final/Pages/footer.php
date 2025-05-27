<?php
// Include database connection
include("connection/connection.php");

// Initialize variables for form data and messages
$name = $email = $message = "";
$errors = [];
$success_message = "";
$redirect = false;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["contact_submit"])) {
    // Sanitize and validate input
    $name = isset($_POST["name"]) ? trim($_POST["name"]) : "";
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $message = isset($_POST["message"]) ? trim($_POST["message"]) : "";

    // Validate name
    if (empty($name)) {
        $errors[] = "Name is required.";
    } elseif (strlen($name) > 100) {
        $errors[] = "Name must be 100 characters or less.";
    }

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (strlen($email) > 100) {
        $errors[] = "Email must be 100 characters or less.";
    }

    // Validate message
    if (empty($message)) {
        $errors[] = "Message is required.";
    } elseif (strlen($message) > 1000) {
        $errors[] = "Message must be 1000 characters or less.";
    }

    // If no errors, insert data into the database
    if (empty($errors)) {
        $sql = "INSERT INTO CONTACT_MESSAGES (message_id, name, email, message, submission_date) 
                VALUES (CONTACT_MESSAGES_SEQ.NEXTVAL, :name, :email, :message, SYSDATE)";
        $stmt = oci_parse($conn, $sql);
        
        // Bind parameters
        oci_bind_by_name($stmt, ':name', $name);
        oci_bind_by_name($stmt, ':email', $email);
        oci_bind_by_name($stmt, ':message', $message);

        // Execute the query
        if (oci_execute($stmt)) {
            $success_message = "Your message has been sent successfully!";
            $redirect = true; // Flag to trigger JavaScript redirect
            // Clear form inputs
            $name = $email = $message = "";
        } else {
            $error = oci_error($stmt);
            $errors[] = "Error saving message: " . htmlspecialchars($error['message']);
        }
        
        oci_free_statement($stmt);
    }
    // If there are errors, retain the submitted values for display
    else {
        $name = htmlspecialchars($name);
        $email = htmlspecialchars($email);
        $message = htmlspecialchars($message);
    }
}
?>

<footer class="footer">
    <div class="container">
        <div class="columns">
            <div class="column is-half1">
                <div class="footer-logo">
                    <a>
                        <img src="CleckFax_Traders_Hub_Logo_group6-removebg-preview.png" alt="Cleckfax Traders Logo" class="footer-logo-img">
                    </a>
                </div>
                <p class="title is-4">Cleckfax Traders Hub</p>
                <p>Email: <a href="mailto:info@cleckfaxtraders.com">info@cleckfaxtraders.com</a></p>
                <p>Phone: <a href="tel:+16466755074">646-675-5074</a></p>
                <p>3961 Smith Street, West Yorkshire, England</p>
                <div class="buttons mt-4">
                    <a href="https://www.facebook.com/CleckfaxTraders" class="button is-small" target="_blank">
                        <span class="icon"><i class="fab fa-facebook-f"></i></span>
                    </a>
                    <a href="https://www.twitter.com/CleckfaxTraders" class="button is-small" target="_blank">
                        <span class="icon"><i class="fab fa-twitter"></i></span>
                    </a>
                    <a href="https://www.instagram.com/CleckfaxTraders" class="button is-small" target="_blank">
                        <span class="icon"><i class="fab fa-instagram"></i></span>
                    </a>
                </div>
            </div>
            <div class="column is-half">
                <h2 class="title is-4" style="margin-left: -70px;">Contact Us</h2>
                <?php if (!empty($success_message)): ?>
                    <div class="notification is-success">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="notification is-danger">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" id="contact-form">
                    <div class="field">
                        <label class="label" for="name">Name</label>
                        <div class="control">
                            <input class="input" type="text" id="name" name="name" placeholder="Name" value="<?php echo isset($name) ? $name : ''; ?>" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <div class="control">
                            <input class="input" type="email" id="email" name="email" placeholder="Email" value="<?php echo isset($email) ? $email : ''; ?>" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="message">Message</label>
                        <div class="control">
                            <textarea class="textarea" id="message" name="message" placeholder="Type your message here..." required><?php echo isset($message) ? $message : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary" type="submit" name="contact_submit">Send</button>
                        </div>
                    </div>
                </form>
                <?php if ($redirect): ?>
                    <script>
                        // Redirect to the same page to prevent form resubmission
                        window.location.href = '<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>';
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <style>
        /* Footer container */
        .footer {
            background-color: #f0f0f0;
            padding: 1rem 0;
            font-size: 0.9rem;
            width: 100%;
            margin: 0; /* Remove any default margin */
        }

        /* Override Bulma container constraints */
        .footer .container {
            width: 100%;
            padding: 0;
            margin: 0;
            max-width: 100%; /* Explicitly override Bulma's max-width */
            box-sizing: border-box; /* Ensure padding doesn't add to width */
        }

        .footer .columns {
            margin-left: 0;
            margin-right: 0;
            width: 100%;
            padding: 0 1rem; /* Add internal padding for content spacing */
        }

        .footer .column {
            padding: 0.5rem;
        }

        /* Logo size */
        .footer-logo-img {
            width: 250px;
            height: auto;
            display: block;
            margin-bottom: 0.5rem;
        }

        /* Social media buttons */
        .footer .buttons {
            margin-top: 0.75rem;
        }

        .footer .button {
            padding: 0.3rem 0.5rem;
            font-size: 0.8rem;
            margin-right: 0.25rem;
        }

        /* Form spacing */
        .footer form .field {
            margin-bottom: 0.75rem;
        }

        .footer input.input,
        .footer textarea.textarea {
            font-size: 0.85rem;
            padding: 0.5rem;
        }

        .column.is-half1 {
            margin-left: 0;
        }

        /* Adjust the form button to match the image */
        .footer .button.is-primary {
            background-color: #00c4b4;
            border: none;
            border-radius: 4px;
            text-transform: uppercase;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .footer .button.is-primary:hover {
            background-color: #00b3a4;
        }

        /* Responsive adjustments for footer */
        @media (max-width: 768px) {
            .footer .columns {
                flex-direction: column;
            }

            .footer .column.is-half,
            .footer .column.is-half1 {
                width: 100%;
                margin-left: 0;
            }

            .footer h2.title {
                margin-left: 0 !important;
            }
        }

        /* Notification styling */
        .notification {
            margin-bottom: 1rem;
        }

        .notification.is-success {
            background-color: #48c774;
            color: #fff;
        }

        .notification.is-danger {
            background-color: #f14668;
            color: #fff;
        }

        .notification ul {
            margin: 0;
            padding-left: 1.5rem;
        }
    </style>
</footer>
<?php
// Close database connection
oci_close($conn);
?>