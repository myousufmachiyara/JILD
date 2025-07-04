
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>@yield('title', 'ERP | Login')</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

		<!-- Web Fonts  -->
		<link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">

		<!-- Vendor CSS -->
        
		<link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/animate/animate.compat.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/font-awesome/css/all.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/boxicons/css/boxicons.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/magnific-popup/magnific-popup.css') }}" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/datatables/media/css/dataTables.bootstrap5.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/select2/css/select2.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/select2-bootstrap-theme/select2-bootstrap.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/bootstrap-multiselect/css/bootstrap-multiselect.css') }}" />
		<link rel="stylesheet" href="{{ asset('/assets/vendor/dropzone/basic.css') }}"/>
		<link rel="stylesheet" href="{{ asset('/assets/vendor/dropzone/dropzone.css') }}" />
		<!-- Theme CSS -->
        <link rel="stylesheet" href="{{ asset('/assets/css/theme.css') }}" />
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

		<!-- Skin CSS -->
        <link rel="stylesheet" href="{{ asset('/assets/css/skins/default.css') }}" />

		<!-- Theme Custom CSS -->
        <link rel="stylesheet" href="{{ asset('/assets/css/custom.css') }}" />
        
    </head>
    
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">{{ __('Login') }}</div>

                        <div class="card-body">
                            <form method="POST" action="{{ route('login') }}">
                                @csrf

                                <div class="row mb-3">
                                    <label for="email" class="col-md-4 col-form-label text-md-end">{{ __('Email Address') }}</label>

                                    <div class="col-md-6">
                                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

                                        @error('email')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Password') }}</label>

                                    <div class="col-md-6">
                                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                                        @error('password')
                                            <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row mb-0">
                                    <div class="col-md-8 offset-md-4">
                                        <button type="submit" class="btn btn-primary">
                                            {{ __('Login') }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
