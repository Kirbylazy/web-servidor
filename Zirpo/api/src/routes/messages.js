import { Router } from 'express'
import { getMessages, sendMessage } from '../controllers/messagesController.js'

const router = Router()

router.get('/:tripId', getMessages)
router.post('/', sendMessage)

export default router
