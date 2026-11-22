<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\Admin\AssignmentController;
use App\Http\Controllers\Api\Admin\AnnouncementController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\MapTrackingController;
use App\Http\Controllers\Api\Admin\OperationalNotificationController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\Client\RepairRequestController as ClientRepairRequestController;
use App\Http\Controllers\Api\Client\FeedbackController as ClientFeedbackController;
use App\Http\Controllers\Api\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Api\RepairRequestController;
use App\Http\Controllers\Api\TechnicianController;
use App\Http\Controllers\Api\Technician\JobController;
use App\Http\Controllers\Api\Technician\ProfileController as TechnicianProfileController;
use App\Http\Controllers\Api\AnnouncementFeedController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Client\IssueCategoryController;
use App\Http\Controllers\Api\Admin\JobController as AdminJobController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
      Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy.',
    ]);
});
    /*
    |--------------------------------------------------------------------------
    | PUBLIC ROUTES
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});



      Route::post('/register', [
    AuthController::class,
    'register',
]);




        Route::post('/login', [
            AuthController::class,
            'login',
        ]);

    });

    Route::get('/locations/barangays', [LocationController::class, 'barangays']);
    Route::get('/locations/puroks', [LocationController::class, 'puroks']);

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | AUTHENTICATION
        |--------------------------------------------------------------------------
        */

        Route::get('/auth/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/auth/logout', [
            AuthController::class,
            'logout',
        ]);


        /*
        |--------------------------------------------------------------------------
        | ADMIN ROUTES
        |--------------------------------------------------------------------------
        |
        | Only users with the Admin role can access these routes.
        |
        */

        Route::middleware('role:Admin')
            ->prefix('admin')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | ADMIN TEST
                |--------------------------------------------------------------------------
                */
                Route::get(
                      '/jobs',
                     [
                     AdminJobController::class,
                     'index',
                      ]
                       );

                Route::get(
                '/jobs/{assignmentId}',
                  [
                 AdminJobController::class,
                  'show',
                    ]
                    );

                Route::get('/test', function () {
                    return response()->json([
                        'success' => true,
                        'message' => 'Admin authorization is working.',
                    ]);
                });

                Route::get('/announcements', [AnnouncementController::class, 'index']);
                Route::post('/announcements', [AnnouncementController::class, 'store']);
                Route::get('/announcements/{announcementId}', [AnnouncementController::class, 'show']);
                Route::put('/announcements/{announcementId}', [AnnouncementController::class, 'update']);
                Route::patch('/announcements/{announcementId}', [AnnouncementController::class, 'update']);
                Route::post('/announcements/{announcementId}/publish', [AnnouncementController::class, 'publish']);
                Route::post('/announcements/{announcementId}/archive', [AnnouncementController::class, 'archive']);
                Route::get('/announcements/{announcementId}/attachment', [AnnouncementController::class, 'attachment'])
                    ->name('admin.announcements.attachment');
                Route::delete('/announcements/{announcementId}', [AnnouncementController::class, 'destroy']);
                Route::get('/notifications', [OperationalNotificationController::class, 'index']);
                Route::get('/notifications/unread-count', [OperationalNotificationController::class, 'unreadCount']);
                Route::patch('/notifications/read-all', [OperationalNotificationController::class, 'markAllRead']);
                Route::patch('/notifications/{notificationId}/read', [OperationalNotificationController::class, 'markRead']);

                Route::get('/feedback', [AdminFeedbackController::class, 'index']);
                Route::get('/reports', [AdminReportController::class, 'index']);
                Route::get('/reports/export', [AdminReportController::class, 'export']);
                Route::get('/map-tracking', [MapTrackingController::class, 'index']);
                /*
                |--------------------------------------------------------------------------
                | CLIENT MANAGEMENT
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'clients',
                    ClientController::class
                )->except([
                    'destroy',
                ]);

                Route::patch(
                    '/clients/{clientId}/status',
                    [
                        ClientController::class,
                        'updateStatus',
                    ]
                );


                /*
                |--------------------------------------------------------------------------
                | TECHNICIAN MANAGEMENT
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'technicians',
                    TechnicianController::class
                )->except([
                    'destroy',
                ]);

                Route::patch(
                    '/technicians/{technicianId}/status',
                    [
                        TechnicianController::class,
                        'updateStatus',
                    ]
                );

                Route::patch(
                    '/technicians/{technicianId}/availability',
                    [
                        TechnicianController::class,
                        'updateAvailability',
                    ]
                );


                /*
                |--------------------------------------------------------------------------
                | SERVICE REQUEST MANAGEMENT
                |--------------------------------------------------------------------------
                |
                | Admin can:
                | - View all service requests
                | - View a specific service request
                |
                | Admin does NOT create service requests.
                | Only the Client can create a service request.
                |
                */

                Route::apiResource(
                    'repair-requests',
                    RepairRequestController::class
                )->only([
                    'index',
                    'show',
                ]);

                Route::patch(
                    '/repair-requests/{requestId}/priority',
                    [RepairRequestController::class, 'updatePriority']
                );


                /*
                |--------------------------------------------------------------------------
                | JOB ASSIGNMENT
                |--------------------------------------------------------------------------
                |
                | Admin can:
                | - Manually assign a technician
                | - Automatically assign the nearest technician
                |
                */

                Route::post(
                    '/repair-requests/{requestId}/assign',
                    [
                        AssignmentController::class,
                        'store',
                    ]
                );

                Route::post(
                    '/repair-requests/{requestId}/auto-assign',
                    [
                        AssignmentController::class,
                        'autoAssign',
                    ]
                );

            });


        /*
        |--------------------------------------------------------------------------
        | TECHNICIAN ROUTES
        |--------------------------------------------------------------------------
        |
        | Only users with the Technician role can access these routes.
        |
        */

        Route::middleware('role:Technician')
            ->prefix('technician')
            ->group(function () {

                Route::get('/profile', [TechnicianProfileController::class, 'show']);
                Route::put('/profile', [TechnicianProfileController::class, 'update']);
                Route::post('/profile/photo', [TechnicianProfileController::class, 'updatePhoto']);
                Route::get('/notifications', [UserNotificationController::class, 'index']);
                Route::get('/notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
                Route::patch('/notifications/read-all', [UserNotificationController::class, 'markAllRead']);
                Route::patch('/notifications/{notificationId}/read', [UserNotificationController::class, 'markRead']);
                Route::get('/announcements', [AnnouncementFeedController::class, 'index']);
                Route::get('/announcements/unread-count', [AnnouncementFeedController::class, 'unreadCount']);
                Route::patch('/announcements/read-all', [AnnouncementFeedController::class, 'markAllRead']);
                Route::patch('/announcements/{announcementId}/read', [AnnouncementFeedController::class, 'markRead']);
                Route::get(
                    '/announcements/{announcementId}/attachment',
                    [AnnouncementFeedController::class, 'attachment']
                )->name('technician.announcements.attachment');

                /*
                |--------------------------------------------------------------------------
                | TECHNICIAN JOBS
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/jobs',
                    [
                        JobController::class,
                        'index',
                    ]
                );

                Route::get(
                    '/jobs/{assignmentId}',
                    [
                        JobController::class,
                        'show',
                    ]
                );

                Route::patch(
                    '/jobs/{assignmentId}/status',
                    [
                        JobController::class,
                        'updateStatus',
                    ]
                );

                Route::patch(
                    '/location',
                    [JobController::class, 'updateLocation']
                );

            });


        /*
        |--------------------------------------------------------------------------
        | CLIENT ROUTES
        |--------------------------------------------------------------------------
        |
        | Only users with the Client role can access these routes.
        |
        */

        Route::middleware('role:Client')
            ->prefix('client')
            ->group(function () {
                Route::get('/profile', [ClientProfileController::class, 'show']);
                Route::put('/profile', [ClientProfileController::class, 'update']);
                Route::post('/profile/photo', [ClientProfileController::class, 'updatePhoto']);
                Route::get('/notifications', [UserNotificationController::class, 'index']);
                Route::get('/notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
                Route::patch('/notifications/read-all', [UserNotificationController::class, 'markAllRead']);
                Route::patch('/notifications/{notificationId}/read', [UserNotificationController::class, 'markRead']);
                Route::get('/announcements', [AnnouncementFeedController::class, 'index']);
                Route::get('/announcements/unread-count', [AnnouncementFeedController::class, 'unreadCount']);
                Route::patch('/announcements/read-all', [AnnouncementFeedController::class, 'markAllRead']);
                Route::patch('/announcements/{announcementId}/read', [AnnouncementFeedController::class, 'markRead']);
                Route::get(
                    '/announcements/{announcementId}/attachment',
                    [AnnouncementFeedController::class, 'attachment']
                )->name('client.announcements.attachment');

                Route::post(
                    '/repair-requests/{requestId}/feedback',
                    [ClientFeedbackController::class, 'store']
                );
                Route::post(
        '/repair-requests/{repairRequest}/cancel',
        [ClientRepairRequestController::class, 'cancel']
    );
                Route::get('/issue-categories', [
                    IssueCategoryController::class,
                    'index',
                ]);
                /*
                |--------------------------------------------------------------------------
                | CLIENT SERVICE REQUESTS
                |--------------------------------------------------------------------------
                |
                | Client can:
                | - View their service requests
                | - View a specific service request
                | - Create a new service request
                |
                */

                Route::apiResource(
                    'repair-requests',
                    ClientRepairRequestController::class
                )->only([
                    'index',
                    'show',
                    'store',
                ]);

            });

    });

});