<?php

namespace App\Providers;


use Illuminate\Support\ServiceProvider;

// Repository Interfaces
use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\AnnotationRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\SkipActivityRepositoryInterface;
use App\Repositories\Contracts\AudioFileRepositoryInterface;
use App\Repositories\Contracts\DimensionValueRepositoryInterface;
use App\Repositories\Contracts\AnnotationDimensionRepositoryInterface;
use App\Repositories\Contracts\BatchRepositoryInterface;

// Repository Implementations
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Repositories\TaskRepository;
use App\Repositories\AnnotationRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\SkipActivityRepository;
use App\Repositories\AudioFileRepository;
use App\Repositories\DimensionValueRepository;
use App\Repositories\AnnotationDimensionRepository;
use App\Repositories\BatchRepository;


// Models
use App\Models\Batch;
use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\SkipActivity;
use App\Models\AudioFile;
use App\Models\DimensionValue;
use App\Models\AnnotationDimension;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repository interfaces to their implementations
        $this->app->bind(ProjectRepositoryInterface::class, function ($app) {
            return new ProjectRepository(new Project());
        });
        $this->app->bind(BatchRepositoryInterface::class, function ($app) {
            return new BatchRepository(new Batch());
        });

        $this->app->bind(UserRepositoryInterface::class, function ($app) {
            return new UserRepository(new User());
        });

        $this->app->bind(TaskRepositoryInterface::class, function ($app) {
            return new TaskRepository(new Task());
        });

        $this->app->bind(AnnotationRepositoryInterface::class, function ($app) {
            return new AnnotationRepository(new Annotation());
        });

        $this->app->bind(ReviewRepositoryInterface::class, function ($app) {
            return new ReviewRepository(new Review());
        });

        $this->app->bind(SkipActivityRepositoryInterface::class, function ($app) {
            return new SkipActivityRepository(new SkipActivity());
        });

        $this->app->bind(AudioFileRepositoryInterface::class, function ($app) {
            return new AudioFileRepository(new AudioFile());
        });

        $this->app->bind(DimensionValueRepositoryInterface::class, function ($app) {
            return new DimensionValueRepository(new DimensionValue());
        });

        $this->app->bind(AnnotationDimensionRepositoryInterface::class, function ($app) {
            return new AnnotationDimensionRepository(new AnnotationDimension());
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}