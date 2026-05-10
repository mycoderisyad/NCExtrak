import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export interface ExtractSyncResult {
  format: string
  targetFolder: string
  fileCount: number
  folderCount: number
  totalBytes: number
}

export interface ExtractResponse {
  mode: 'sync' | 'async'
  status: 'done' | 'queued'
  jobId?: number
  result?: ExtractSyncResult
}

export interface ExtractJobStatus {
  id: number
  state: 'queued' | 'running' | 'done' | 'failed'
  progress: number
  error: string | null
  result: ExtractSyncResult | null
}

const ocsHeaders = {
  'OCS-APIRequest': 'true',
}

const unwrapOcsData = <T>(payload: unknown): T => {
  const response = payload as {
    ocs?: {
      data?: T
    }
  }

  return (response.ocs?.data ?? response) as T
}

export const requestExtract = async (
  fileId: number,
  overwrite = false,
): Promise<ExtractResponse> => {
  const url = generateOcsUrl('/apps/ncextrak/api/v1/extract')
  const response = await axios.post(
    url,
    {
      fileId,
      overwrite,
    },
    { headers: ocsHeaders },
  )

  return unwrapOcsData<ExtractResponse>(response.data)
}

export const getExtractJobStatus = async (jobId: number): Promise<ExtractJobStatus> => {
  const url = generateOcsUrl(`/apps/ncextrak/api/v1/jobs/${jobId}`)
  const response = await axios.get(url, { headers: ocsHeaders })
  return unwrapOcsData<ExtractJobStatus>(response.data)
}
