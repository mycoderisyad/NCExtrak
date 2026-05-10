import { showError, showLoading, showSuccess, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { Permission, registerFileAction, type IFileAction, type INode } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

import { getExtractJobStatus, requestExtract, type ExtractJobStatus } from '../api/extractClient'
import { isArchiveMime } from '../util/mime'

const EXTRACT_ICON = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
  <path d="M5 3h11l5 5v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm10 1.5V9h4.5"/>
  <path d="M12 11v6m0 0l-3-3m3 3l3-3"/>
</svg>
`.trim()

const archiveNamePattern = /\.(zip|rar|7z|tar|tgz|tbz2|gz|bz2)$/i

type ToastHandle = ReturnType<typeof showLoading>

const isArchiveCandidate = (node: INode): boolean => {
  if (isArchiveMime(node.mime)) {
    return true
  }
  return archiveNamePattern.test(node.basename ?? '')
}

const canExtract = (node: INode): boolean => {
  const perms = typeof node.permissions === 'number' ? node.permissions : 0
  return (perms & Permission.UPDATE) !== 0 || (perms & Permission.CREATE) !== 0
}

const refreshFileView = (): void => {
  const globalObject = window as Window & {
    OCA?: {
      Files?: {
        App?: {
          fileList?: { reload?: () => void }
          currentFileList?: { reload?: () => void }
        }
        fileList?: { reload?: () => void }
      }
    }
  }

  const filesApp = globalObject.OCA?.Files
  filesApp?.App?.currentFileList?.reload?.()
  filesApp?.App?.fileList?.reload?.()
  filesApp?.fileList?.reload?.()
}

const dismissToast = (toast: ToastHandle | null): void => {
  if (toast === null) {
    return
  }
  try {
    toast.hideToast()
  } catch {
    // toast may already be hidden
  }
}

const formatProgressMessage = (fileName: string, status: ExtractJobStatus): string => {
  const stateLabel =
    status.state === 'queued' ? t('ncextrak', 'queued') : t('ncextrak', 'extracting')
  const percent = Math.max(0, Math.min(99, status.progress ?? 0))
  return t('ncextrak', '{name}: {state} {percent}%', {
    name: fileName,
    state: stateLabel,
    percent: String(percent),
  })
}

const sleep = (ms: number): Promise<void> =>
  new Promise((resolve) => window.setTimeout(resolve, ms))

const pollJobUntilFinished = async (jobId: number, fileName: string): Promise<void> => {
  let toast: ToastHandle | null = showLoading(
    t('ncextrak', '{name}: queued 0%', { name: fileName }),
    { timeout: TOAST_PERMANENT_TIMEOUT },
  )
  let lastShownPercent = -1
  let lastShownState: ExtractJobStatus['state'] | null = null

  const maxDurationMs = 30 * 60 * 1000
  const start = Date.now()
  let delayMs = 1500

  try {
    while (Date.now() - start < maxDurationMs) {
      await sleep(delayMs)
      delayMs = Math.min(delayMs + 500, 4000)

      let status: ExtractJobStatus
      try {
        status = await getExtractJobStatus(jobId)
      } catch {
        continue
      }

      if (status.state === 'done') {
        dismissToast(toast)
        toast = null
        showSuccess(
          t('ncextrak', 'Archive extracted to {folder}', {
            folder: status.result?.targetFolder ?? '',
          }),
        )
        refreshFileView()
        return
      }

      if (status.state === 'failed') {
        dismissToast(toast)
        toast = null
        showError(
          t('ncextrak', 'Archive extraction failed: {error}', { error: status.error ?? '' }),
        )
        return
      }

      const percent = Math.max(0, Math.min(99, status.progress ?? 0))
      if (percent !== lastShownPercent || status.state !== lastShownState) {
        dismissToast(toast)
        toast = showLoading(formatProgressMessage(fileName, status), {
          timeout: TOAST_PERMANENT_TIMEOUT,
        })
        lastShownPercent = percent
        lastShownState = status.state
      }
    }

    dismissToast(toast)
    toast = null
    showError(t('ncextrak', 'Archive extraction is still running, please check back later'))
  } finally {
    dismissToast(toast)
  }
}

const runExtract = async (node: INode): Promise<boolean | null> => {
  const fileId = typeof node.fileid === 'number' ? node.fileid : 0
  if (fileId <= 0) {
    showError(t('ncextrak', 'Unable to resolve target file ID'))
    return null
  }

  const fileName = node.basename ?? ''

  try {
    const result = await requestExtract(fileId)
    if (result.mode === 'sync' && result.result) {
      showSuccess(t('ncextrak', 'Extracted to {folder}', { folder: result.result.targetFolder }))
      refreshFileView()
      return true
    }

    if (result.mode === 'async' && typeof result.jobId === 'number') {
      void pollJobUntilFinished(result.jobId, fileName)
      return true
    }
  } catch {
    showError(t('ncextrak', 'Archive extraction failed'))
    return false
  }

  return null
}

export const registerExtractAction = (): void => {
  const action: IFileAction = {
    id: 'ncextrak-extract',
    displayName: () => t('ncextrak', 'Extract here'),
    iconSvgInline: () => EXTRACT_ICON,
    order: 25,
    enabled: ({ nodes }) => {
      const [node] = nodes
      if (!node || nodes.length !== 1) {
        return false
      }
      return isArchiveCandidate(node) && canExtract(node)
    },
    exec: async ({ nodes }) => {
      const [node] = nodes
      if (!node) {
        return null
      }
      return runExtract(node)
    },
    execBatch: async ({ nodes }) => Promise.all(nodes.map((node) => runExtract(node))),
  }

  registerFileAction(action)
}
