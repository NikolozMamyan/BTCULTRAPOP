<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class WebComponentManager
{
    private const UPLOAD_PREFIX = 'uploads/web-components';
    private const MAX_SIZE = 5_242_880;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/web_components/home.json')]
        private readonly string $configPath,
        #[Autowire('%kernel.project_dir%/public/uploads/web-components')]
        private readonly string $uploadDirectory,
        private readonly Filesystem $filesystem,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        $stored = $this->readStoredConfig();
        $defaults = self::defaultHome();

        $hero = $this->normalizeItems($stored['hero'] ?? null);
        $heroSettings = $this->normalizeHeroSettings($stored['hero_settings'] ?? null);
        $licenses = $this->normalizeItems($stored['licenses'] ?? null);
        $newsletter = $this->normalizeNewsletter($stored['newsletter'] ?? null);
        $boutique = $this->normalizeBoutique($stored['boutique'] ?? null);

        return [
            'hero' => array_key_exists('hero', $stored) ? $hero : $defaults['hero'],
            'hero_settings' => array_key_exists('hero_settings', $stored) ? $heroSettings : $defaults['hero_settings'],
            'licenses' => array_key_exists('licenses', $stored) ? $licenses : $defaults['licenses'],
            'newsletter' => array_key_exists('newsletter', $stored) ? $newsletter : $defaults['newsletter'],
            'boutique' => array_key_exists('boutique', $stored) ? $boutique : $defaults['boutique'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function boutiqueHeroImages(): array
    {
        $heroes = $this->home()['boutique']['heroes'] ?? [];
        $images = [];

        foreach ($heroes as $key => $hero) {
            if (!is_array($hero)) {
                continue;
            }

            $image = trim((string) ($hero['image'] ?? ''));

            if ('' !== $image) {
                $images[(string) $key] = $image;
            }
        }

        return $images;
    }

    /**
     * @return array<string, string>
     */
    public function boutiqueHeroMobileImages(): array
    {
        $heroes = $this->home()['boutique']['heroes'] ?? [];
        $images = [];

        foreach ($heroes as $key => $hero) {
            if (!is_array($hero)) {
                continue;
            }

            $image = trim((string) ($hero['mobile_image'] ?? ''));

            if ('' !== $image) {
                $images[(string) $key] = $image;
            }
        }

        return $images;
    }

    public function boutiqueHeroMobileBreakpoint(): string
    {
        return (string) ($this->home()['boutique']['mobile_breakpoint'] ?? '995px');
    }

    /**
     * @param list<UploadedFile> $files
     */
    public function addImages(string $section, array $files): int
    {
        $this->assertImageListSection($section);

        $home = $this->home();
        $added = 0;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || '' === $file->getClientOriginalName()) {
                continue;
            }

            $path = $this->upload($section, $file);
            $home[$section][] = 'hero' === $section
                ? $this->createHeroSlide($path, $this->labelFromFilename($file->getClientOriginalName()))
                : $this->createLicenseItem($path, $this->labelFromFilename($file->getClientOriginalName()));
            ++$added;
        }

        if ($added > 0) {
            $this->write($home);
        }

        return $added;
    }

    public function deleteImage(string $section, string $path): bool
    {
        $this->assertImageListSection($section);
        $path = trim($path);

        if ('' === $path) {
            return false;
        }

        $home = $this->home();
        $initialCount = count($home[$section]);
        $mobileImageToRemove = '';

        if ('hero' === $section) {
            foreach ($home[$section] as $item) {
                if (($item['image'] ?? '') === $path) {
                    $mobileImageToRemove = (string) ($item['mobile_image'] ?? '');
                    break;
                }
            }
        }

        $home[$section] = array_values(array_filter(
            $home[$section],
            static fn (array $item): bool => ($item['image'] ?? '') !== $path,
        ));

        if ($initialCount === count($home[$section])) {
            return false;
        }

        $this->removeUploadedFile($path);
        $this->removeUploadedFile($mobileImageToRemove);
        $this->write($home);

        return true;
    }

    public function updateHeroMobileImage(int $index, UploadedFile $file): void
    {
        if ('' === $file->getClientOriginalName()) {
            throw new \InvalidArgumentException('admin.web_components.flash.no_file');
        }

        $home = $this->home();

        if (!isset($home['hero'][$index]) || !is_array($home['hero'][$index])) {
            throw new \InvalidArgumentException('admin.web_components.flash.invalid_hero_slide');
        }

        $oldPath = (string) ($home['hero'][$index]['mobile_image'] ?? '');
        $home['hero'][$index]['mobile_image'] = $this->upload('hero-mobile-'.$index, $file);

        $this->removeUploadedFile($oldPath);
        $this->write($home);
    }

    public function updateHeroMobileBreakpoint(string $breakpoint): void
    {
        $home = $this->home();
        $home['hero_settings']['mobile_breakpoint'] = $this->normalizeBreakpoint($breakpoint);

        $this->write($home);
    }

    public function updateNewsletterImage(UploadedFile $file): void
    {
        if ('' === $file->getClientOriginalName()) {
            throw new \InvalidArgumentException('admin.web_components.flash.no_file');
        }

        $home = $this->home();
        $oldPath = $home['newsletter']['image'] ?? '';
        $home['newsletter']['image'] = $this->upload('newsletter', $file);

        $this->removeUploadedFile($oldPath);
        $this->write($home);
    }

    public function updateBoutiqueHeroImage(string $key, UploadedFile $file): void
    {
        $defaults = self::defaultHome()['boutique']['heroes'];

        if (!isset($defaults[$key])) {
            throw new \InvalidArgumentException('admin.web_components.flash.invalid_boutique_hero');
        }

        if ('' === $file->getClientOriginalName()) {
            throw new \InvalidArgumentException('admin.web_components.flash.no_file');
        }

        $home = $this->home();
        $oldPath = (string) ($home['boutique']['heroes'][$key]['image'] ?? '');
        $home['boutique']['heroes'][$key] = array_merge(
            $defaults[$key],
            is_array($home['boutique']['heroes'][$key] ?? null) ? $home['boutique']['heroes'][$key] : [],
            ['image' => $this->upload('boutique-'.$key, $file)],
        );

        $this->removeUploadedFile($oldPath);
        $this->write($home);
    }

    public function updateBoutiqueHeroMobileImage(string $key, UploadedFile $file): void
    {
        $defaults = self::defaultHome()['boutique']['heroes'];

        if (!isset($defaults[$key])) {
            throw new \InvalidArgumentException('admin.web_components.flash.invalid_boutique_hero');
        }

        if ('' === $file->getClientOriginalName()) {
            throw new \InvalidArgumentException('admin.web_components.flash.no_file');
        }

        $home = $this->home();
        $oldPath = (string) ($home['boutique']['heroes'][$key]['mobile_image'] ?? '');
        $home['boutique']['heroes'][$key] = array_merge(
            $defaults[$key],
            is_array($home['boutique']['heroes'][$key] ?? null) ? $home['boutique']['heroes'][$key] : [],
            ['mobile_image' => $this->upload('boutique-mobile-'.$key, $file)],
        );

        $this->removeUploadedFile($oldPath);
        $this->write($home);
    }

    public function updateBoutiqueHeroMobileBreakpoint(string $breakpoint): void
    {
        $home = $this->home();
        $home['boutique']['mobile_breakpoint'] = $this->normalizeBreakpoint($breakpoint);

        $this->write($home);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultHome(): array
    {
        return [
            'hero_settings' => [
                'mobile_breakpoint' => '995px',
            ],
            'hero' => [
                [
                    'image' => 'img/home/hero-collection.png',
                    'alt_key' => 'home.carousel.banner_one_alt',
                    'fallback_key' => 'home.carousel.collection_fallback',
                    'fallback_color' => '#e82118',
                    'badge_key' => 'home.carousel.new_collection',
                    'title_key' => 'home.carousel.hero_title',
                    'text_key' => 'home.carousel.hero_text',
                    'action_key' => 'home.carousel.discover',
                    'action_tone' => 'primary',
                ],
                [
                    'image' => 'img/home/hero-limited.png',
                    'alt_key' => 'home.carousel.banner_two_alt',
                    'fallback_key' => 'home.carousel.limited_fallback',
                    'fallback_color' => '#203263',
                    'badge_key' => 'home.carousel.limited',
                    'title_key' => 'home.carousel.limited_title',
                    'text_key' => 'home.carousel.limited_text',
                    'action_key' => 'home.carousel.view_shop',
                    'action_tone' => 'secondary',
                ],
            ],
            'licenses' => [
                ['name' => 'One Piece', 'filter' => 'One Piece', 'image' => 'img/licenses/MENU_LICENCE-01.png'],
                ['name' => 'Naruto Shippuden', 'filter' => 'Naruto', 'image' => 'img/licenses/MENU_LICENCE-02.png'],
                ['name' => 'Dragon Ball Z', 'filter' => 'Dragon Ball Z', 'image' => 'img/licenses/MENU_LICENCE-03.png'],
                ['name' => 'Dragon Ball Super', 'filter' => 'Dragon Ball Super', 'image' => 'img/licenses/MENU_LICENCE-04.png'],
                ['name' => 'Arcane', 'filter' => 'Arcane', 'image' => 'img/licenses/MENU_LICENCE-05.png'],
                ['name' => 'Demon Slayer', 'filter' => 'Demon Slayer', 'image' => 'img/licenses/MENU_LICENCE-06.png'],
                ['name' => 'Jujutsu Kaisen', 'filter' => 'Jujutsu Kaisen', 'image' => 'img/licenses/MENU_LICENCE-07.png'],
            ],
            'newsletter' => [
                'image' => 'img/home/newsletter.jpg',
            ],
            'boutique' => [
                'mobile_breakpoint' => '995px',
                'heroes' => [
                    'all' => [
                        'label' => 'admin.web_components.boutique.hero.all',
                        'image' => 'img/boutique/boutique_tout.jpg',
                    ],
                    'drinks' => [
                        'label' => 'admin.web_components.boutique.hero.drinks',
                        'image' => 'img/boutique/boutique_boissons.jpg',
                    ],
                    'savory' => [
                        'label' => 'admin.web_components.boutique.hero.savory',
                        'image' => 'img/boutique/boutique_salée.jpg',
                    ],
                    'sweet' => [
                        'label' => 'admin.web_components.boutique.hero.sweet',
                        'image' => 'img/boutique/boutique_sucrée.jpg',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readStoredConfig(): array
    {
        if (!$this->filesystem->exists($this->configPath)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($this->configPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param mixed $items
     *
     * @return list<array<string, string>>
     */
    private function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $image = trim((string) ($item['image'] ?? ''));

            if ('' === $image) {
                continue;
            }

            $normalized[] = array_filter(
                array_map(static fn (mixed $value): string => trim((string) $value), $item),
                static fn (string $value): bool => '' !== $value,
            );
        }

        return $normalized;
    }

    /**
     * @param mixed $newsletter
     *
     * @return array{image: string}
     */
    private function normalizeNewsletter(mixed $newsletter): array
    {
        $default = self::defaultHome()['newsletter'];

        if (!is_array($newsletter)) {
            return $default;
        }

        $image = trim((string) ($newsletter['image'] ?? ''));

        return ['image' => '' !== $image ? $image : $default['image']];
    }

    /**
     * @param mixed $settings
     *
     * @return array{mobile_breakpoint: string}
     */
    private function normalizeHeroSettings(mixed $settings): array
    {
        $default = self::defaultHome()['hero_settings'];

        if (!is_array($settings)) {
            return $default;
        }

        try {
            return [
                'mobile_breakpoint' => $this->normalizeBreakpoint((string) ($settings['mobile_breakpoint'] ?? '')),
            ];
        } catch (\InvalidArgumentException) {
            return $default;
        }
    }

    /**
     * @param mixed $boutique
     *
     * @return array{mobile_breakpoint: string, heroes: array<string, array<string, string>>}
     */
    private function normalizeBoutique(mixed $boutique): array
    {
        $default = self::defaultHome()['boutique'];

        if (!is_array($boutique)) {
            return $default;
        }

        $heroes = [];
        $storedHeroes = is_array($boutique['heroes'] ?? null) ? $boutique['heroes'] : [];
        $mobileBreakpoint = $default['mobile_breakpoint'];

        try {
            $mobileBreakpoint = $this->normalizeBreakpoint((string) ($boutique['mobile_breakpoint'] ?? ''));
        } catch (\InvalidArgumentException) {
        }

        foreach ($default['heroes'] as $key => $defaultHero) {
            $storedHero = is_array($storedHeroes[$key] ?? null) ? $storedHeroes[$key] : [];
            $image = trim((string) ($storedHero['image'] ?? ''));
            $mobileImage = trim((string) ($storedHero['mobile_image'] ?? ''));
            $label = trim((string) ($storedHero['label'] ?? ''));

            $heroes[$key] = [
                'label' => '' !== $label ? $label : $defaultHero['label'],
                'image' => '' !== $image ? $image : $defaultHero['image'],
            ];

            if ('' !== $mobileImage) {
                $heroes[$key]['mobile_image'] = $mobileImage;
            }
        }

        return [
            'mobile_breakpoint' => $mobileBreakpoint,
            'heroes' => $heroes,
        ];
    }

    /**
     * @param array<string, mixed> $home
     */
    private function write(array $home): void
    {
        $this->filesystem->mkdir(dirname($this->configPath));
        file_put_contents(
            $this->configPath,
            json_encode($home, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    private function upload(string $section, UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('admin.web_components.flash.invalid_file');
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('admin.web_components.flash.too_large');
        }

        $mimeType = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? null;

        if (null === $extension) {
            throw new \InvalidArgumentException('admin.web_components.flash.unsupported');
        }

        $this->filesystem->mkdir($this->uploadDirectory);

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = sprintf(
            '%s-%s-%s.%s',
            $section,
            strtolower((string) $this->slugger->slug($originalName ?: 'image')),
            bin2hex(random_bytes(8)),
            $extension,
        );

        $file->move($this->uploadDirectory, $filename);

        return self::UPLOAD_PREFIX . '/' . $filename;
    }

    private function removeUploadedFile(string $path): void
    {
        $path = trim($path);

        if (!str_starts_with($path, self::UPLOAD_PREFIX . '/')) {
            return;
        }

        $absolutePath = sprintf('%s/%s', rtrim($this->uploadDirectory, '/\\'), basename($path));

        if ($this->filesystem->exists($absolutePath)) {
            $this->filesystem->remove($absolutePath);
        }
    }

    /**
     * @return array<string, string>
     */
    private function createHeroSlide(string $path, string $label): array
    {
        return [
            'image' => $path,
            'alt' => $label,
            'fallback' => $label,
            'fallback_color' => '#e82118',
            'badge_key' => 'home.carousel.new_collection',
            'title_key' => 'home.carousel.hero_title',
            'text_key' => 'home.carousel.hero_text',
            'action_key' => 'home.carousel.discover',
            'action_tone' => 'primary',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function createLicenseItem(string $path, string $label): array
    {
        return [
            'name' => $label,
            'filter' => $label,
            'image' => $path,
        ];
    }

    private function labelFromFilename(string $filename): string
    {
        $label = pathinfo($filename, PATHINFO_FILENAME);
        $label = trim(preg_replace('/[-_]+/', ' ', $label) ?? '');

        return '' !== $label ? mb_convert_case($label, MB_CASE_TITLE, 'UTF-8') : 'ULTRAPOP';
    }

    private function normalizeBreakpoint(string $breakpoint): string
    {
        $breakpoint = strtolower(trim($breakpoint));

        if (preg_match('/^\d{2,4}$/', $breakpoint) === 1) {
            $breakpoint .= 'px';
        }

        if (preg_match('/^(\d{2,4})px$/', $breakpoint, $matches) !== 1) {
            throw new \InvalidArgumentException('admin.web_components.flash.invalid_breakpoint');
        }

        $value = (int) $matches[1];

        if ($value < 320 || $value > 1600) {
            throw new \InvalidArgumentException('admin.web_components.flash.invalid_breakpoint');
        }

        return $value.'px';
    }

    private function assertImageListSection(string $section): void
    {
        if (!in_array($section, ['hero', 'licenses'], true)) {
            throw new \InvalidArgumentException('Invalid web component section.');
        }
    }
}
