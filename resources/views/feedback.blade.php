<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Feedback Form - {{ $restaurantSettingName ?? 'Restaurant' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('/') }}public/front/assets/css/feedback-form.css">
</head>
<body class="feedback-page">
@php
    $feedbackDate = optional($order->order_time ? \Carbon\Carbon::parse($order->order_time) : $order->created_at)->format('d M Y');
    $feedbackTime = optional($order->order_time ? \Carbon\Carbon::parse($order->order_time) : $order->created_at)->format('h:i A');
    $ratingRows = [
        'food_rating' => 'Food',
        'service_rating' => 'Service',
        'cleanliness_rating' => 'Cleanliness',
        'atmosphere_rating' => 'Atmosphere',
        'value_rating' => 'Value',
        'overall_experience_rating' => 'Overall Experience',
    ];
    $ratingOptions = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Great'];
@endphp

<div class="feedback-sheet">
    <div class="feedback-title">Feedback Form</div>

    <div class="feedback-meta">
        <div class="feedback-meta-grid">
            <div><strong>Date:</strong> {{ $feedbackDate }}</div>
            <div class="text-end"><strong>Time:</strong> {{ $feedbackTime }}</div>
            <div class="feedback-meta-server">
                <strong>Name of the Server:</strong>
                <input class="feedback-meta-server-input" type="text" name="server_name" form="order-feedback-form" value="{{ old('server_name', $serverName ?? '') }}" maxlength="255" aria-label="Name of the Server">
            </div>
        </div>
    </div>

    @if(session('feedback_success'))
        <div class="alert alert-success feedback-alert">{{ session('feedback_success') }}</div>
    @elseif(session('feedback_info'))
        <div class="alert alert-info feedback-alert">{{ session('feedback_info') }}</div>
    @endif

    @if($feedbackSubmitted)
        <div class="feedback-success-box">
            <div class="feedback-success-icon">✓</div>
            <h2>Thank You</h2>
            <p>Your feedback for order <strong>#{{ $order->order_number }}</strong> has already been submitted.</p>
            <div class="feedback-thanks mb-0">Thank you for your time and input<br>We hope to see you soon again!</div>
        </div>
    @else
        <form id="order-feedback-form" method="POST" action="{{ route('feedback.submit', ['token' => $order->feedback_token]) }}" novalidate>
            @csrf

            @if($errors->any())
                <div class="alert alert-danger feedback-alert">
                    Please select one option for every review row and check the contact information you entered.
                </div>
            @endif

            <table class="feedback-rating-table" aria-label="Feedback ratings">
                <thead>
                    <tr>
                        <th scope="col">Review</th>
                        @foreach($ratingOptions as $optionLabel)
                            <th scope="col">{{ $optionLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($ratingRows as $field => $label)
                        <tr>
                            <th scope="row">{{ $label }}</th>
                            @foreach($ratingOptions as $value => $optionLabel)
                                <td>
                                    <input
                                        class="feedback-choice"
                                        type="radio"
                                        name="{{ $field }}"
                                        value="{{ $value }}"
                                        aria-label="{{ $label }} - {{ $optionLabel }}"
                                        {{ (string) old($field) === (string) $value ? 'checked' : '' }}
                                        required
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <section class="feedback-section feedback-comment-section">
                <h2 class="feedback-section-title">Comment</h2>
                <textarea
                    id="feedback_comment"
                    class="feedback-comment-textarea"
                    name="comment"
                    rows="4"
                    maxlength="2000"
                    placeholder="Write your comment here..."
                >{{ old('comment') }}</textarea>
            </section>

            <section class="feedback-section">
                <h2 class="feedback-section-title">How Did You Hear About Us?</h2>
                @php $heardOld = old('heard_from', []); @endphp
                <label class="feedback-source-row">
                    <span>Family and Friends</span>
                    <input class="feedback-choice" type="checkbox" name="heard_from[]" value="family_friends" {{ in_array('family_friends', $heardOld, true) ? 'checked' : '' }}>
                </label>
                <label class="feedback-source-row">
                    <span>Social Media</span>
                    <input class="feedback-choice" type="checkbox" name="heard_from[]" value="social_media" {{ in_array('social_media', $heardOld, true) ? 'checked' : '' }}>
                </label>
                <label class="feedback-source-row">
                    <span>Passing-by</span>
                    <input class="feedback-choice" type="checkbox" name="heard_from[]" value="passing_by" {{ in_array('passing_by', $heardOld, true) ? 'checked' : '' }}>
                </label>
            </section>

            <section class="feedback-section">
                <h2 class="feedback-section-title">Sign Up For Gift Vouchers / VIP Rewards</h2>
                <div class="feedback-contact-row">
                    <label for="guest_name">Name:</label>
                    <input id="guest_name" class="feedback-line-input" type="text" name="guest_name" value="{{ old('guest_name') }}" maxlength="255">
                </div>
                <div class="feedback-contact-row">
                    <label for="guest_email">Email:</label>
                    <input id="guest_email" class="feedback-line-input" type="email" name="guest_email" value="{{ old('guest_email') }}" maxlength="255">
                </div>
                <div class="feedback-contact-row">
                    <label for="guest_phone">Phone:</label>
                    <input id="guest_phone" class="feedback-line-input" type="text" name="guest_phone" value="{{ old('guest_phone') }}" maxlength="30">
                </div>
            </section>

            <div class="feedback-thanks">
                Thank you for your time and input<br>
                We hope to see you soon again!
            </div>

            <button type="submit" class="btn feedback-submit">Submit Feedback</button>
        </form>
    @endif
</div>
</body>
</html>
