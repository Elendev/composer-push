<?php


namespace Elendev\NexusComposerPush;

use Composer\Composer;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\InputInterface;

class Configuration
{
    /**
     * @var array config cache
     */
    private $config = null;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    const PUSH_CFG_NAME = 'name';

    public function __construct(InputInterface $input, Composer $composer, IOInterface $io)
    {
        $this->input = $input;
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Get the Nexus extra values if available
     * @param $parameter
     * @param null $default
     * @return mixed|null
     */
    public function get($parameter, $default = null)
    {
        if ($this->config === null) {
            $this->config = $this->parseNexusExtra($this->input, $this->composer);
        }

        if (array_key_exists($parameter, $this->config) && $this->config[$parameter] !== null) {
            return $this->config[$parameter];
        } else {
            return $default;
        }
    }

    /**
     * Return the package name based on the given name or the real package name.
     *
     * @param \Symfony\Component\Console\Input\InputInterface|null $input
     *
     * @return string
     */
    public function getPackageName()
    {
        if ($this->input && $this->input->getOption('name')) {
            return $this->input->getOption('name');
        } else {
            return $this->composer->getPackage()->getName();
        }
    }

    /**
     * Return the repository URL, based on the configuration or the user input
     * @return string
     */
    public function getUrl()
    {
        $url = $this->input->getOption('url');

        if (empty($url)) {
            $url = $this->get('url');

            if (empty($url)) {
                throw new \InvalidArgumentException('The option --url is required or has to be provided as an extra argument in composer.json');
            }
        }

        return $url;
    }

    /**
     * Return the package version
     * @return string
     */
    public function getVersion()
    {
        return $this->input->getArgument('version');
    }

    /**
     * Return the source type
     * @return bool|string|string[]|null
     */
    public function getSourceType()
    {
        return $this->input->getOption('src-type');
    }

    /**
     * Return the source URL
     * @return bool|string|string[]|null
     */
    public function getSourceUrl()
    {
        return $this->input->getOption('src-url');
    }

    /**
     * Return the source reference
     * @return bool|string|string[]|null
     */
    public function getSourceReference()
    {
        return $this->input->getOption('src-ref');
    }

    /**
     * Return the username given in parameters during call
     * @return string|null
     */
    public function getOptionUsername()
    {
        return $this->input->getOption('username');
    }

    /**
     * Return the password given in parameters during call
     * @return string|null
     */
    public function getOptionPassword()
    {
        return $this->input->getOption('password');
    }

    /**
     * Type of repository. Default: nexus (lowercase)
     * @return string
     */
    public function getType()
    {
        $type = $this->input->getOption('type');

        if (empty($type)) {
            $type = $this->get('type', 'nexus');
        }

        return $type;
    }

    /**
     * @return boolean
     */
    public function getVerifySsl() {
        $verifySsl = $this->input->getOption('ssl-verify');

        if ($verifySsl === null) {
            $verifySsl = $this->get('ssl-verify', true);
        }

        return filter_var($verifySsl, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Fetch any directories or files to be excluded from zip creation
     *
     * @return array
     */
    public function getIgnores()
    {
        // Remove after removal of --ignore-dirs option
        $deprecatedIgnores = $this->getDirectoriesToIgnore($this->input);

        $optionalIgnore = $this->input->getOption('ignore');
        $composerIgnores = $this->get('ignore', []);
        $gitAttrIgnores = $this->getGitAttributesExportIgnores($this->input);
        $composerJsonIgnores = $this->getComposerJsonArchiveExcludeIgnores($this->input);

        if (! $this->input->getOption('keep-vendor')) {
            $defaultIgnores = ['vendor/'];
        } else {
            $defaultIgnores = [];
        }

        $ignore = array_merge($deprecatedIgnores, $composerIgnores, $optionalIgnore, $gitAttrIgnores, $composerJsonIgnores, $defaultIgnores);
        return array_unique($ignore);
    }

    /**
     * @param InputInterface $input
     * @deprecated argument has been changed to ignore
     * @return array
     */
    private function getDirectoriesToIgnore(InputInterface $input)
    {
        $optionalIgnore = $input->getOption('ignore-dirs') ?? [];
        $composerIgnores = $this->get('ignore-dirs', []);

        if (!empty($optionalIgnore)) {
            $this->io->write('<error>The --ignore-dirs option has been deprecated. Please use --ignore instead</error>');
        }

        if (!empty($composerIgnores)) {
            $this->io->write('<error>The ignore-dirs config option has been deprecated. Please use ignore instead</error>');
        }

        $ignore = array_merge($composerIgnores, $optionalIgnore);
        return array_unique($ignore);
    }

    private function getGitAttributesExportIgnores(InputInterface $input)
    {
        $option = $input->getOption('ignore-by-git-attributes');
        $extra = $this->get('ignore-by-git-attributes', false);
        if (!$option && !$extra) {
            return [];
        }

        $path = getcwd() . '/.gitattributes';
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $lines = explode(PHP_EOL, $contents);
        $ignores = [];
        foreach ($lines as $line) {
            if ($line = trim($line)) {
                // ignore if end with `export-ignore`
                $diff = strlen($line) - 13;
                if ($diff > 0 && strpos($line, 'export-ignore', $diff) !== false) {
                    $ignores[] = trim(trim(explode(' ', $line)[0]), DIRECTORY_SEPARATOR);
                }
            }
        }

        return $ignores;
    }

    private function getComposerJsonArchiveExcludeIgnores(InputInterface $input)
    {
        $option = $input->getOption('ignore-by-composer');
        $extra = $this->get('ignore-by-composer', false);
        if (!$option && !$extra) {
            return [];
        }

        $ignores = [];
        foreach ($this->composer->getPackage()->getArchiveExcludes() as $exclude) {
            $ignores[] = trim($exclude, DIRECTORY_SEPARATOR);
        }

        return $ignores;
    }

    /**
     * @param InputInterface $input
     */
    private function parseNexusExtra(InputInterface $input, Composer $composer)
    {
        $this->checkNexusPushValid($input, $composer);

        $repository = $input->getOption(PushCommand::REPOSITORY);
        $extras = $composer->getPackage()->getExtra();

        $extrasConfigurationKey = 'push';

        if (empty($extras['push'])) {
            if (!empty($extras['nexus-push'])) {
                $extrasConfigurationKey = 'nexus-push';
                $this->io->warning('Configuration under extra - nexus-push in composer.json is deprecated, please replace it by extra - push');
            }
        }

        if (empty($repository)) {
            // configurations in composer.json support Only upload to unique repository
            if (!empty($extras[$extrasConfigurationKey])) {
                return $extras[$extrasConfigurationKey];
            }
        } else {
            // configurations in composer.json support upload to multi repository
            foreach ($extras[$extrasConfigurationKey] as $key=> $nexusPushConfigItem) {
                if (empty($nexusPushConfigItem[self::PUSH_CFG_NAME])) {
                    $fmt = 'The push configuration array in composer.json with index {%s} need provide value for key "%s"';
                    $exceptionMsg = sprintf($fmt, $key, self::PUSH_CFG_NAME);
                    throw new InvalidConfigException($exceptionMsg);
                }
                if ($nexusPushConfigItem[self::PUSH_CFG_NAME] ==$repository) {
                    return $nexusPushConfigItem;
                }
            }

            if (empty($this->nexusPushConfig)) {
                throw new \InvalidArgumentException('The value of option --repository match no push configuration, please check');
            }
        }

        return [];
    }

    private function checkNexusPushValid(InputInterface $input, Composer $composer)
    {
        $repository = $input->getOption(PushCommand::REPOSITORY);
        $extras = $composer->getPackage()->getExtra();
        if (empty($repository) && (!empty($extras['push'][0]) || !empty($extras['nexus-push'][0]))) {
            throw new \InvalidArgumentException('As configurations in composer.json support upload to multi repository, the option --repository is required');
        }
        if (!empty($repository) && empty($extras['push'][0]) && empty($extras['nexus-push'][0])) {
            throw new InvalidConfigException('the option --repository is offered, but configurations in composer.json doesn\'t support upload to multi repository, please check');
        }
    }
}
