<?php

namespace Tomatexyz\PelicanThemeStudio\Providers;

use Illuminate\Support\ServiceProvider;

class PelicanThemeStudioServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->mergeConfigFrom(
      __DIR__ . "/../../config/pelican-theme-studio.php",
      "pelican-theme-studio",
    );
  }

  public function boot(): void {}
}
