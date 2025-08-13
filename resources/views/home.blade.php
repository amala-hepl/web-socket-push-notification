@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>
                @if( (Auth::user()->role == 1) && isset($all_users))
                    <div class="card-body">
                        <h5>All Users:</h5>
                        <ul>
                            @foreach($all_users as $user)
                                <li>{{ $user->name }} ({{ $user->email }})</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- New Users Section -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5>New Users (Last 24 Hours) 
                        <button id="refreshNewUsers" class="btn btn-sm btn-primary float-end">Refresh</button>
                    </h5>
                </div>
                <div class="card-body">
                    <div id="loadingSpinner" class="text-center" style="display: none;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="newUsersContainer">
                        <p class="text-muted">Loading new users...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('pro_js/jquery_3.7.1.min.js') }}"></script>
<script>
    // jQuery document ready
    $(document).ready(function() {
        // alert('Welcome to the Dashboard!');
        // console.log('Dashboard loaded with jQuery');
        
        // Load new users on page load
        loadNewUsers();
        
        // Refresh button click event
        $('#refreshNewUsers').click(function() {
            loadNewUsers();
        });
    });
    
    // Function to load new users via AJAX
    function loadNewUsers() {
        // Show loading spinner
        $('#loadingSpinner').show();
        $('#newUsersContainer').hide();
        
        // AJAX call to get new users
        $.ajax({
            url: '{{ route("get.new.users") }}',
            type: 'GET',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                console.log('AJAX Success:', response);
                
                // Hide loading spinner
                $('#loadingSpinner').hide();
                $('#newUsersContainer').show();
                
                // Display password verification result
                let html = '';
                // if (response.password_check) {
                //     html += '<div class="alert alert-info mb-3">';
                //     html += '<h6>Password Verification Test:</h6>';
                //     html += '<p><strong>Result:</strong> ' + response.password_check + '</p>';
                //     if (response.hash_info) {
                //         html += '<small>';
                //         html += '<strong>Algorithm:</strong> ' + response.hash_info.algorithm + '<br>';
                //         html += '<strong>Variant:</strong> ' + response.hash_info.variant + '<br>';
                //         html += '<strong>Cost:</strong> ' + response.hash_info.cost + '<br>';
                //         html += '<strong>Tested Password:</strong> ' + response.hash_info.tested_password;
                //         html += '</small>';
                //     }
                //     html += '</div>';
                // }
                
                if (response.success && response.users.length > 0) {
                    html += '<h6>Found ' + response.count + ' new user(s):</h6>';
                    html += '<ul class="list-group">';
                    
                    response.users.forEach(function(user) {
                        let createdDate = new Date(user.created_at).toLocaleString();
                        html += '<li class="list-group-item d-flex justify-content-between align-items-center">';
                        html += '<div>';
                        html += '<strong>' + user.name + '</strong><br>';
                        html += '<small class="text-muted">' + user.email + '</small>';
                        html += '</div>';
                        html += '<small class="text-muted">Joined: ' + createdDate + '</small>';
                        html += '</li>';
                    });
                    
                    html += '</ul>';
                } else {
                    html += '<p class="text-muted">No new users found in the last hour.</p>';
                }
                
                $('#newUsersContainer').html(html);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                // Hide loading spinner
                $('#loadingSpinner').hide();
                $('#newUsersContainer').show();
                
                $('#newUsersContainer').html('<div class="alert alert-danger">Error loading new users: ' + error + '</div>');
            }
        });
    }
</script>
@endsection
