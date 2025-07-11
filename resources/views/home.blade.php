@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row">
        <div>
            <h2 class="text-dark"><strong id="currentDate"></strong></h2>
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