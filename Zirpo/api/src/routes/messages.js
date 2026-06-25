import { Router } from 'express'
import { getConversations, getMessages, sendMessage } from '../controllers/messagesController.js'
import authenticate from '../middleware/auth.js'

const router = Router()

router.get('/conversations', authenticate, getConversations)
router.get('/:tripId/:passengerId', authenticate, getMessages)
router.post('/', authenticate, sendMessage)

export default router
