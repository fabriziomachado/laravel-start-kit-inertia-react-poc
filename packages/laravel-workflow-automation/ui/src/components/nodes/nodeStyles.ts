import type { NodeType } from '../../api/types'
import {
  Zap,
  Play,
  GitBranch,
  ArrowRightLeft,
  Settings,
  Wrench,
  Code,
  StickyNote,
  type LucideIcon,
} from 'lucide-react'

export const NODE_TYPE_ICON: Record<NodeType, LucideIcon> = {
  trigger:     Zap,
  action:      Play,
  condition:   GitBranch,
  transformer: ArrowRightLeft,
  control:     Settings,
  utility:     Wrench,
  code:        Code,
  annotation:  StickyNote,
}
