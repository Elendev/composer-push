workflow "On push" {
  on = "push"
  resolves = ["docker://php:7.2-cli"]
}

action "Composer install" {
  uses = "docker://composer:1.8"
  args = "install"
}

action "PHP CS Fixer" {
  uses = "docker://php:7.2-cli"
  needs = ["composer"]
  args = "vendor/bin/php-cs-fixer fix src --dry-run"
}
