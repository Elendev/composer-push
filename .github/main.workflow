workflow "New workflow" {
  on = "push"
  resolves = ["docker://php:7.2-cli"]
}

action "composer" {
  uses = "docker://composer:1.8"
  args = "install"
}

action "docker://php:7.2-cli" {
  uses = "docker://php:7.2-cli"
  needs = ["composer"]
  args = "vendor/bin/php-cs-fixer fix src --dry-run"
}
