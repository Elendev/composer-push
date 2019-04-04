workflow "On push" {
  on = "push"
  resolves = ["PHP CS Fixer"]
}

action "Composer install" {
  uses = "docker://composer:1.8"
  args = "install"
}

action "PHP CS Fixer" {
  uses = "docker://php:7.2-cli"
  needs = ["Composer install"]
  args = "vendor/bin/php-cs-fixer fix src --dry-run"
}
