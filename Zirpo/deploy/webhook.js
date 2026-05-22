import http from 'http'
import crypto from 'crypto'
import { execSync } from 'child_process'

const PORT = process.env.WEBHOOK_PORT || 9000
const SECRET = process.env.WEBHOOK_SECRET || ''
const PROJECT_DIR = process.env.PROJECT_DIR || '/mnt/m2/www/default/Zirpo'

function verifySignature(payload, signature) {
  if (!SECRET) return true
  const hmac = crypto.createHmac('sha256', SECRET)
  const digest = 'sha256=' + hmac.update(payload).digest('hex')
  return crypto.timingSafeEqual(Buffer.from(digest), Buffer.from(signature))
}

function deploy() {
  const timestamp = new Date().toISOString()
  console.log(`[${timestamp}] Deploy iniciado...`)

  try {
    const cmds = [
      `cd ${PROJECT_DIR} && sudo git fetch origin && sudo git reset --hard origin/main`,
      `cd ${PROJECT_DIR}/app && sudo npm install && sudo npm run build`,
      `pm2 restart zirpo-api`
    ]

    for (const cmd of cmds) {
      console.log(`> ${cmd}`)
      const output = execSync(cmd, { encoding: 'utf8', timeout: 120000 })
      if (output.trim()) console.log(output.trim())
    }

    console.log(`[${timestamp}] Deploy completado OK`)
  } catch (err) {
    console.error(`[${timestamp}] Deploy FALLÓ:`, err.message)
  }
}

const server = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/webhook') {
    let body = ''
    req.on('data', chunk => { body += chunk })
    req.on('end', () => {
      const sig = req.headers['x-hub-signature-256'] || ''
      if (SECRET && !verifySignature(body, sig)) {
        console.log('Webhook rechazado: firma inválida')
        res.writeHead(403)
        return res.end('Forbidden')
      }

      try {
        const payload = JSON.parse(body)
        if (payload.ref === 'refs/heads/main') {
          res.writeHead(200)
          res.end('Deploy started')
          deploy()
        } else {
          res.writeHead(200)
          res.end('Ignored (not main branch)')
        }
      } catch {
        res.writeHead(400)
        res.end('Bad request')
      }
    })
  } else {
    res.writeHead(404)
    res.end('Not found')
  }
})

server.listen(PORT, () => {
  console.log(`Webhook server escuchando en puerto ${PORT}`)
})
