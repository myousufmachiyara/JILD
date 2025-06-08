@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row">
        <div>
            <h2 class="text-dark"><strong id="currentDate"></strong></h2>
        </div>
        <div class="col-12 col-md-3 mb-2">
            <section class="card card-featured-left card-featured-primary mt-3">
                <div  style="background-image: url('/assets/img/rec-icon.png') " class="card-body icon-container">
                    <h3 class="amount text-dark"><strong>Total Projects</strong></h3>
                    <h2 class="amount m-0 text-primary actual-data">
                        <strong></strong>
                        <!-- <span class="title text-end text-dark h6"> PKR</span> -->
                    </h2>
                    <div class="summary-footer">
                        <a class="text-primary text-uppercase" href="#">View Details</a>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-md-3 mb-2">
            <section class="card card-featured-left card-featured-danger mt-3">
                <div  style="background-image: url('/assets/img/bank-icon.png') " class="card-body icon-container">
                    <h3 class="amount text-dark"><strong>Pieces In Production</strong></h3>
                    <h2 class="amount m-0 text-danger actual-data">
                        <strong></strong>
                        <!-- <span class="title text-end text-dark h6"> PKR</span> -->
                    </h2>
                    <div class="summary-footer">
                        <a class="text-danger text-uppercase" href="#">View Details</a>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>

		$(document).ready(function() {
			// Get current date and day
			const now = new Date();
			const day = getDaySuffix(now.getDate());
			const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
			const currentDate = now.toLocaleDateString(undefined, options);

			// Format the date as "Thursday, 5th December 2024"
			const formattedDate = `${now.toLocaleString('en-GB', { weekday: 'long' })}, ${day} ${now.toLocaleString('en-GB', { month: 'long' })} ${now.getFullYear()}`;

			// Update UI
			document.getElementById('currentDate').innerText = formattedDate;
		});	

        function getDaySuffix(day) {
			if (day >= 11 && day <= 13) {
			return day + 'th';
			}
			switch (day % 10) {
			case 1: return day + 'st';
			case 2: return day + 'nd';
			case 3: return day + 'rd';
			default: return day + 'th';
			}
		}
    </script>
@endsection