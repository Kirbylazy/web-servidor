import { Router } from 'express'
import { getMessages, sendMessage } from '../controllers/messagesController.js'
import authenticate from '../middleware/auth.js'

const router = Router()

router.get('/:tripId', authenticate, getMessages)
router.post('/', authenticate, sendMessage)

export default router
