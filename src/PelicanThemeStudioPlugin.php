<?php

namespace Tomatexyz\PelicanThemeStudio;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class PelicanThemeStudioPlugin implements HasPluginSettings, Plugin
{
  use EnvironmentWriterTrait;

  public function getId(): string
  {
    return "pelican-theme-studio";
  }

  public function register(Panel $panel): void
  {
    $cfg = config("pelican-theme-studio");
    $panel->colors([
      "primary" => $this->hexToScale($cfg["primary"] ?? "#0053cf"),
      "gray" => $this->hexToScale($cfg["gray"] ?? "#000000"),
      "info" => $this->hexToScale($cfg["info"] ?? "#0ea5e9"),
      "success" => $this->hexToScale($cfg["success"] ?? "#22c55e"),
      "warning" => $this->hexToScale($cfg["warning"] ?? "#f59e0b"),
      "danger" => $this->hexToScale($cfg["danger"] ?? "#ef4444"),
    ]);
    // Force darkmode for color compatibility
    $panel->darkMode(true, true);
  }

  public function boot(Panel $panel): void
  {
    FilamentView::registerRenderHook(
      PanelsRenderHook::HEAD_END,
      function (): string {
        $radius = (string) config("pelican-theme-studio.radius", "0.75rem");

        // Only allow common css length formats for security
        if (
          !preg_match('/^(\d+(\.\d+)?)(px|rem|em|%)$/', trim($radius)) &&
          $radius !== "0"
        ) {
          $radius = "0.50rem";
        }

        // Custom radius injection
        return Blade::render(
          <<<'BLADE'
          <style>
          :root { --ptm-radius: {{ $radius }}; }

          [class^="fi-"],
          [class*=" fi-"] {
            border-radius: var(--ptm-radius) !important;
          }

          .fi-btn,
          .fi-input,
          .fi-select,
          .fi-ta-cell,
          .fi-card,
          .fi-modal {
            border-radius: var(--ptm-radius) !important;
          }
          </style>
          BLADE
          ,
          ["radius" => $radius],
        );
      },
    );
  }

  public function getSettingsForm(): array
  {
    return [
      ColorPicker::make("primary")
        ->label("Primary")
        ->helperText(
          "Used for the main brand accent: primary buttons, active states, highlighted actions.",
        )
        ->required()
        ->default(fn() => config("pelican-theme-studio.primary")),

      ColorPicker::make("gray")
        ->label("Gray")
        ->helperText(
          "Used for most surfaces and text neutrals: backgrounds, cards, borders, separators, secondary text.",
        )
        ->required()
        ->default(fn() => config("pelican-theme-studio.gray")),

      ColorPicker::make("info")
        ->label("Info")
        ->helperText("Used as accent for informational UI.")
        ->required()
        ->default(fn() => config("pelican-theme-studio.info")),

      ColorPicker::make("success")
        ->label("Success")
        ->helperText("Used as accent for success UI.")
        ->required()
        ->default(fn() => config("pelican-theme-studio.success")),

      ColorPicker::make("warning")
        ->label("Warning")
        ->helperText("Used as accent for warning UI.")
        ->required()
        ->default(fn() => config("pelican-theme-studio.warning")),

      ColorPicker::make("danger")
        ->label("Danger")
        ->helperText("Used as accent for destructive UI.")
        ->required()
        ->default(fn() => config("pelican-theme-studio.danger")),

      TextInput::make("radius")
        ->label("Border radius")
        ->helperText(
          "Used for rounding across the whole panel (buttons, inputs, cards ...). Examples: 0px, 6px, 0.75rem, 9999px",
        )
        ->required()
        ->default(fn() => config("pelican-theme-studio.radius")),
    ];
  }

  public function saveSettings(array $data): void
  {
    $this->writeToEnvironment([
      "THEME_PRIMARY" => $data["primary"],
      "THEME_GRAY" => $data["gray"],
      "THEME_INFO" => $data["info"],
      "THEME_SUCCESS" => $data["success"],
      "THEME_WARNING" => $data["warning"],
      "THEME_DANGER" => $data["danger"],
      "THEME_RADIUS" => $data["radius"],
    ]);

    Notification::make()
      ->title("Theme updated")
      ->body("The theme will be applied after the next page reload.")
      ->success()
      ->send();
  }

  // Produce array of colors via single hex value
  private function hexToScale(string $hex): array
  {
    $hex = ltrim(trim($hex), "#");

    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
      $hex = "7c3aed";
    }

    [$r, $g, $b] = [
      hexdec(substr($hex, 0, 2)),
      hexdec(substr($hex, 2, 2)),
      hexdec(substr($hex, 4, 2)),
    ];

    $stops = [
      50 => 0.92,
      100 => 0.84,
      200 => 0.7,
      300 => 0.56,
      400 => 0.42,
      500 => 0.3,
      600 => 0.22,
      700 => 0.14,
      800 => 0.09,
      900 => 0.06,
      950 => 0.04,
    ];

    $scale = [];

    foreach ($stops as $k => $tint) {
      $rr = (int) round($r + (255 - $r) * $tint);
      $gg = (int) round($g + (255 - $g) * $tint);
      $bb = (int) round($b + (255 - $b) * $tint);

      $scale[$k] = sprintf("#%02x%02x%02x", $rr, $gg, $bb);
    }

    $scale[950] = $this->mixWithBlack($scale[900], 0.35);

    return $scale;
  }

  // Darken out dark colors (900/950) to be deeper
  private function mixWithBlack(string $hex, float $amount): string
  {
    $hex = ltrim($hex, "#");

    [$r, $g, $b] = [
      hexdec(substr($hex, 0, 2)),
      hexdec(substr($hex, 2, 2)),
      hexdec(substr($hex, 4, 2)),
    ];

    $rr = (int) round($r * (1 - $amount));
    $gg = (int) round($g * (1 - $amount));
    $bb = (int) round($b * (1 - $amount));

    return sprintf("#%02x%02x%02x", $rr, $gg, $bb);
  }
}
