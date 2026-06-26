import axios from 'axios';

export const isSmsLogRejected = (smsLog) => {
    return Boolean(smsLog?.is_rejected || smsLog?.rejected_at);
};

export const isSmsLogLinkable = (smsLog) => {
    if (smsLog?.order || isSmsLogRejected(smsLog)) {
        return false;
    }

    return smsLog?.parsing_result?.operation_type !== 'out';
};

export const isSmsLogRejectable = (smsLog) => {
    if (smsLog?.order || isSmsLogRejected(smsLog)) {
        return false;
    }

    return smsLog?.parsing_result?.operation_type !== 'out';
};

export const rejectSmsLogRequest = async (smsLogId) => {
    const response = await axios.post(route('sms-logs.reject.store', smsLogId));

    return response.data;
};
