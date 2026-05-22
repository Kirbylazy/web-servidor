module.exports = {
  apps: [{
    name: 'zirpo-webhook',
    script: 'webhook.js',
    cwd: '/var/www/Zirpo/deploy',
    env: {
      WEBHOOK_PORT: 9000,
      WEBHOOK_SECRET: '',
      PROJECT_DIR: '/var/www/Zirpo'
    }
  }]
}
