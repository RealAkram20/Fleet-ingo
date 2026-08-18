<?php

namespace App\Http\Controllers;

use App\Mail\TestMessage;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    private const BRANDING_DIR = 'branding';

    public function edit(): View
    {
        return view('settings.index', [
            'settings' => Settings::allWithDefaults(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'mailConfigured' => (bool) Settings::get('mail_host'),
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'logo.max' => 'The logo must be 2 MB or smaller.',
            'icon.max' => 'The icon must be 2 MB or smaller.',
        ]);

        Setting::putMany([
            'app_name' => $data['app_name'],
            'tagline' => $data['tagline'] ?? '',
        ]);

        if ($request->hasFile('logo')) {
            $old = Settings::get('logo_path');
            Setting::put('logo_path', $path = $this->storeBranding($request->file('logo'), 'logo'));
            $this->removeReplacedBranding($old, $path);
        }

        if ($request->hasFile('icon')) {
            $old = Settings::get('icon_path');
            Setting::put('icon_path', $path = $this->storeBranding($request->file('icon'), 'icon'));
            $this->removeReplacedBranding($old, $path);
            $this->regenerateFavicons(public_path($path));
        }

        return back()->with('status', 'Branding updated.');
    }

    public function updateFleet(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_service_interval_km' => ['required', 'integer', 'min:100', 'max:100000'],
            'default_service_interval_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'due_soon_km' => ['required', 'integer', 'min:0', 'max:10000'],
            'due_soon_days' => ['required', 'integer', 'min:0', 'max:365'],
            'licence_warn_days' => ['required', 'integer', 'min:0', 'max:365'],
            'timezone' => ['required', 'timezone'],
        ]);

        Setting::putMany($data);

        return back()->with('status', 'Fleet defaults updated.');
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:190'],
            'mail_port' => ['required_with:mail_host', 'nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:190'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_from_address' => ['required', 'email', 'max:190'],
            'mail_from_name' => ['required', 'string', 'max:120'],
        ], [
            'mail_port.required_with' => 'A port is needed when an SMTP host is set.',
        ]);

        // A blank password box leaves the stored one alone, so the secret never
        // has to be round-tripped through the form.
        if (($data['mail_password'] ?? '') === '') {
            unset($data['mail_password']);
        }

        $data['mail_encryption'] = ($data['mail_encryption'] ?? 'none') === 'none' ? '' : $data['mail_encryption'];

        Setting::putMany($data);

        return back()->with('status', 'Email settings updated.');
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);

        try {
            Mail::to($data['test_email'])->send(new TestMessage);
        } catch (Throwable $e) {
            return back()->withErrors(['test_email' => 'Could not send: '.$e->getMessage()]);
        }

        $where = config('mail.default') === 'log'
            ? 'No SMTP host is set, so it went to storage/logs/laravel.log instead of being sent.'
            : 'Sent via '.Settings::get('mail_host').'.';

        return back()->with('status', "Test message handled. {$where}");
    }

    private function storeBranding(UploadedFile $file, string $name): string
    {
        $dir = public_path(self::BRANDING_DIR);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // The extension is guessed from the file's content, never taken from the
        // uploaded filename: a "logo.php" with image bytes inside must not land
        // in the web root as an executable script.
        // A changing filename is what busts the browser cache for a new logo.
        $filename = $name.'-'.substr(md5_file($file->getRealPath()), 0, 10).'.'.$file->extension();

        $file->move($dir, $filename);

        return self::BRANDING_DIR.'/'.$filename;
    }

    /**
     * Deletes the file a new upload replaced. Only files this controller wrote
     * (inside public/branding) are touched — the shipped defaults under
     * public/images stay, so "reset to default" remains possible by hand.
     */
    private function removeReplacedBranding(?string $old, string $new): void
    {
        if ($old && $old !== $new && str_starts_with($old, self::BRANDING_DIR.'/') && is_file(public_path($old))) {
            @unlink(public_path($old));
        }
    }

    /** Rebuilds the favicons from a newly uploaded square icon. */
    private function regenerateFavicons(string $source): void
    {
        if (! function_exists('imagecreatefrompng') || ! is_file($source)) {
            return;
        }

        try {
            $info = getimagesize($source);
            $image = match ($info['mime'] ?? '') {
                'image/png' => imagecreatefrompng($source),
                'image/jpeg' => imagecreatefromjpeg($source),
                'image/webp' => imagecreatefromwebp($source),
                default => null,
            };

            if (! $image) {
                return;
            }

            foreach ([32 => 'favicon-32.png', 16 => 'favicon-16.png', 180 => 'apple-touch-icon.png'] as $size => $out) {
                $canvas = imagecreatetruecolor($size, $size);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
                imagepng($canvas, public_path($out), 9);
                imagedestroy($canvas);
            }

            imagedestroy($image);
        } catch (Throwable) {
            // A failed favicon rebuild must not fail the settings save.
        }
    }
}
