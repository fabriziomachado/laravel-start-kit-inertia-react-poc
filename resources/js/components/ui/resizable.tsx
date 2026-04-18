import { GripVertical } from 'lucide-react';
import { Group, Panel, Separator } from 'react-resizable-panels';
import type { GroupProps, PanelProps, SeparatorProps } from 'react-resizable-panels';

import { cn } from '@/lib/utils';

function ResizablePanelGroup({ className, ...props }: GroupProps) {
    return (
        <Group
            className={cn('flex h-full w-full data-[orientation=vertical]:flex-col', className)}
            {...props}
        />
    );
}

function ResizablePanel({ ...props }: PanelProps) {
    return <Panel {...props} />;
}

function ResizableHandle({
    className,
    withHandle,
    ...props
}: SeparatorProps & {
    withHandle?: boolean;
}) {
    return (
        <Separator
            className={cn(
                'bg-border focus-visible:ring-ring relative flex w-px shrink-0 items-center justify-center outline-none',
                'after:absolute after:inset-y-0 after:left-1/2 after:z-0 after:w-4 after:-translate-x-1/2',
                'focus-visible:ring-1 focus-visible:ring-offset-1',
                'data-[separator=inactive]:bg-border/80',
                'data-[separator=focus]:bg-border data-[separator=active]:bg-border',
                className,
            )}
            {...props}
        >
            {withHandle ? (
                <div className="border-border/70 bg-muted text-muted-foreground pointer-events-none absolute z-10 flex h-9 w-2 shrink-0 items-center justify-center rounded-full border shadow-xs">
                    <GripVertical className="size-2.5 opacity-80" strokeWidth={2} aria-hidden />
                </div>
            ) : null}
        </Separator>
    );
}

export { ResizableHandle, ResizablePanel, ResizablePanelGroup };
